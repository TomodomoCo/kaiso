<?php

namespace Tomodomo;

use GuzzleHttp\Psr7;

use Tomodomo\Kaiso\Exceptions\ControllerException;
use Tomodomo\Kaiso\Exceptions\MethodException;

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
     * @var \Pimple\Container
     */
    public $container;

    /**
     * Might be nice to have some settings here!
     *
     * @param array $settings
     *
     * @return void
     */
    public function __construct($settings = [])
    {
        // Merge defaults with custom settings
        $this->settings = array_merge($this->settings, $settings);

        // Create our Pimple container
        $this->container = new \Pimple\Container();

        /**
         * Add settings to the container
         *
         * @psalm-suppress MissingClosureReturnType
         */
        $this->container['settings'] = $this->settings;

        /**
         * Add wp_query to the container so it can be used later
         *
         * @psalm-suppress MissingClosureReturnType
         */
        $this->container['wp_query'] = function () {
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
    public function getTemplateHierarchy()
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
    public function formatControllerName($name)
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
    public function getControllerHierarchy()
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

        // Empty controller to fill in later
        $controller = null;

        // Loop through the possible controllers
        foreach ($controllers as $controllerName) {
            // Check if it exists; instantiate if so
            if (class_exists($controllerName)) {
                $controller = new $controllerName($this->container);

                break;
            }
        }

        // If we didn't get a controller, throw an exception
        if ($controller === null) {
            throw new ControllerException("Could not find controller: {$controllerName}", [
                'controller' => $controllerName,
                'method'     => null,
            ]);
        }

        return $controller;
    }

    /**
     * The heart of the operation — run the app
     *
     * @throws MethodException
     *
     * @return void
     */
    public function run()
    {
        // @todo handle an exception here
        $controller = $this->getController();

        // Grab the server request object
        $request = Psr7\ServerRequest::fromGlobals();

        // @todo Massage WordPress to use a PSR7-compatible response
        $response = null;

        // Pass along query args
        $args = $request->getQueryParams();

        // Get the request method
        $method = strtolower($_SERVER['REQUEST_METHOD']);

        // If the method `any` exists on our found controller, load that.
        // Otherwise, we load a method matching the request method (e.g.
        // get(), post(), etc.
        if (method_exists($controller, 'any')) {
            echo $controller->any($request, $response, $args);
        } elseif (method_exists($controller, $method)) {
            echo $controller->{$method}($request, $response, $args);
        } else {
            $controllerName = get_class($controller);

            throw new MethodException("Could not find method `{$method}` for controller `{$controllerName}`", [
                'controller' => get_class($controllerName),
                'method'     => $method,
            ]);
        }

        exit;
    }

    /**
     * @return void
     */
    public static function registerTemplates(array $templates = [])
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
}
