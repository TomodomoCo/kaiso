<?php

namespace Tomodomo;

use GuzzleHttp\Psr7\Response;
use GuzzleHttp\Psr7\ServerRequest;
use Pimple\Container;
use Tomodomo\Kaiso\Exceptions\ControllerException;
use Tomodomo\Kaiso\Exceptions\MethodException;
use WP_Query;
use Zend\HttpHandlerRunner\Emitter\SapiEmitter;

class Kaiso
{
    /**
     * Default settings
     *
     * @var array
     */
    public $settings = [];

    /**
     * Pimple container!
     *
     * @var Container
     */
    public $container;

    /**
     * Might be nice to have some settings here!
     *
     * @param array $settings
     *
     * @return void
     */
    public function __construct(array $settings = [])
    {
        // Merge defaults with custom settings
        $this->settings = array_merge($this->settings, $settings);

        // Create our Pimple container
        $this->container = new \Pimple\Container();

        /**
         * Add settings to the container.
         */
        $this->container['settings'] = $this->settings;

        /**
         * Add wp_query to the container so it can be used later.
         */
        $this->container['wp_query'] = function () : WP_Query {
            global $wp_query;

            return $wp_query;
        };

        return;
    }

    /**
     * Fetch the template hierarchy array from brain/hierarchy
     *
     * @return array
     */
    public function getTemplateHierarchy() : array
    {
        $hierarchy = new \Brain\Hierarchy\Hierarchy();

        return $hierarchy->getTemplates($this->container['wp_query']);
    }

    /**
     * Format WP-style template names into FancyPhpController names.
     *
     * @param string $name
     *
     * @return string
     */
    public function formatControllerName(string $name) : string
    {
        $name = \Stringy\Stringy::create($name)->removeRight('.php');

        if ($name->startsWith('404') || $name->startsWith('500')) {
            $name = $name->ensureLeft('error-');
        }

        return (string) $name->upperCamelize()->append('Controller');
    }

    /**
     * Iterate over the template hierarchy and format it
     * as controllers and paths
     *
     * @return array
     */
    public function getControllerHierarchy() : array
    {
        $templates = $this->getTemplateHierarchy();

        $controllers = [];

        foreach ($templates as $template) {
            $path = $this->settings['controllerPath'];
            $name = $this->formatControllerName($template);

            $controllers[] = $path . $name;
        }

        return $controllers;
    }

    /**
     * Find a working controller, or throw an exception
     *
     * @throws ControllerException
     *
     * @return object
     */
    public function getController()
    {
        // Fetch our formatted list of controller names
        $controllers = $this->getControllerHierarchy();

        // Loop through the possible controllers
        foreach ($controllers as $controllerName) {
            // Continue through the loop if we can't find the controller
            if (!class_exists($controllerName)) {
                continue;
            }

            // Return the first matching controller we find
            return new $controllerName($this->container);
        }

        // If we didn't get a controller, throw an exception
        throw new ControllerException("Could not find controller: {$controllerName}", [
            'controller' => $controllerName,
            'method'     => null,
        ]);
    }

    /**
     * The heart of the operation — run the app
     *
     * @return void
     */
    public function run() : void
    {
        // @todo handle an exception here
        $controller = $this->getController();

        // Grab the server request object and instantiate a response
        $request  = ServerRequest::fromGlobals();
        $response = new Response();

        // Pass along query args
        $args = $request->getQueryParams();

        // Get the request method
        $method = $this->getMethodForController($controller);

        $response = $controller->{$method}($request, $response, $args);

        // Emit a response
        (new SapiEmitter())->emit($response);

        exit;
    }

    /**
     * Register custom template controllers so WordPress sees them.
     *
     * @return void
     */
    public static function registerTemplates(array $templates = []) : void
    {
        $customTemplates = [];

        // Build the array
        foreach ($templates as $template) {
            foreach ($template['postTypes'] as $type) {
                $customTemplates[$type]["{$template['slug']}-template.php"] = $template['name'];
            }
        }

        foreach ($customTemplates as $postType => $postTypeTemplates) {
            // Register in the appropriate hook
            add_filter("theme_{$postType}_templates", function (array $templates) use ($postTypeTemplates) {
                return $postTypeTemplates;
            });
        }

        return;
    }

    /**
     * Get a handler method for the given controller class. If we
     * can find a method specifically matching the HTTP verb (e.g.
     * `get()`, `post()`, etc), use that. Otherwise, fall back to
     * `any()`, and throw an exception if no handler is found.
     *
     * @param object $controller
     *
     * @throws MethodException
     *
     * @return string
     */
    public function getMethodForController($controller) : string
    {
        $method = strtolower($_SERVER['REQUEST_METHOD']);

        if (method_exists($controller, $method)) {
            return $method;
        }

        if (method_exists($controller, 'any')) {
            return 'any';
        }

        $controllerName = get_class($controller);

        throw new MethodException("Could not find method `{$method}()` for controller `{$controllerName}`", [
            'controller' => $controllerName,
            'method'     => $method,
        ]);
    }
}
