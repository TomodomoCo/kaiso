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
     * Default settings.
     *
     * @var array
     */
    public $settings = [];

    /**
     * A standard Pimple container.
     *
     * @var Container
     */
    public $container;

    /**
     * Get the party started.
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
     * Fetch the template hierarchy array for the current
     * request from `brain/hierarchy`.
     *
     * @return array
     */
    public function getTemplateHierarchy() : array
    {
        $hierarchy = new \Brain\Hierarchy\Hierarchy();

        return $hierarchy->getTemplates($this->container['wp_query']);
    }

    /**
     * Format WordPress-style template names into camel-cased,
     * PSR-2, `FancyPhpController` names.
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
     * Iterate over the template hierarchy and format it into
     * an array of namespaced controllers.
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
     * Find a working controller, or throw an exception.
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
     * Get a handler method for the given controller class. If we can
     * find a method specifically matching the HTTP verb (e.g.
     * `get()`, `post()`, etc), use that. Otherwise, fall back to
     * `any()`, or throw an exception if no handler is found.
     *
     * @param object $controller
     *
     * @throws MethodException
     *
     * @return string
     */
    public function getHandlerMethodForController($controller) : string
    {
        $method = strtolower($_SERVER['REQUEST_METHOD'] ?? null);

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

    /**
     * Build HTML to render the error message. Partial frivolity.
     *
     * @param string $message
     *
     * @return string
     */
    public function buildErrorMessageHtml(string $message) : string
    {
        // Allow line breaks at backslashes
        $message = str_replace('\\', '<wbr>\\', esc_html($message));

        // Make it pretty
        $css = <<<CSS
<style type="text/css">
.kaiso-error.kaiso-error--dark {
  --kaiso-background: #002b36;
  --kaiso-title: #fdf6e3;
  --kaiso-message: #93a1a1;
}

.kaiso-error.kaiso-error--light {
  --kaiso-background: #fdf6e3;
  --kaiso-title: #073642;
  --kaiso-message: #586e75;
}

.kaiso-error {
  transition: background ease-in-out 0.3s;
  margin-top: 3em;
  padding: 1px 1.5em;
  border-radius: 0.25em;
  background: var(--kaiso-background);
}

.kaiso-error__title {
  transition: color ease-in-out 0.3s;
  color: var(--kaiso-title);
}

.kaiso-error__message {
  transition: color ease-in-out 0.3s;
  padding-left: 2em;
  text-indent: -2em;
  color: var(--kaiso-message);
}
</style>
CSS;

        // This is useless and fun
        $js = <<<JS
<script type="text/javascript">
var el = document.querySelector('.kaiso-error')

el.addEventListener('click', function () {
  el.classList.toggle('kaiso-error--dark')
  el.classList.toggle('kaiso-error--light')
})
</script>
JS;

        // Build the whole operation
        $html = '';
        $html .= $css;
        $html .= '<div class="kaiso-error kaiso-error--dark">';
        $html .= '<h4 class="kaiso-error__title">Kaiso Error Message</h4>';
        $html .= '<p class="kaiso-error__message"><code>' . $message . '</code></p>';
        $html .= '</div>';
        $html .= $js;

        return $html;
    }

    /**
     * The heart of the operation — run the app
     *
     * @return void
     */
    public function run() : void
    {
        try {
            $controller = $this->getController();
            $method     = $this->getHandlerMethodForController($controller);
        } catch (\Exception $e) {
            $html = '<h1>Uh oh…</h1>';
            $html .= '<p>We couldn’t load the page you requested.</p>';

            // If the user wants errors displayed, add one to the output
            if ($this->container['settings']['displayErrors'] ?? false) {
                $html .= $this->buildErrorMessageHtml($e->getMessage());
            }

            // Use WordPress' built in wp_die handler to display the error
            wp_die($html);

            exit;
        }

        // Grab the server request object and instantiate a response
        $request  = ServerRequest::fromGlobals();
        $response = new Response();

        // Pass along query args
        $args = $request->getQueryParams();

        // Call the request handler
        $response = $controller->{$method}($request, $response, $args);

        // Emit the response
        (new SapiEmitter())->emit($response);

        exit;
    }
}
