<?php

namespace TomodomoCo;

use GuzzleHttp\Psr7;

/*
 * Kaiso is the meat and potatoes of this whole operation.
 */
class Kaiso {
	/**
	 * Default settings
	 *
	 * @var array
	 */
	public $settings = [];

	/**
	 * Pimple container!
	 *
	 * @var Pimple
	 */
	public $container;

	/**
	 * Might be nice to have some settings here!
	 *
	 * @return void
	 */
	public function __construct($settings = []) {
		// Merge defaults with custom settings
		$this->settings = array_merge($this->settings, $settings);

		// Create our Pimple container
		$this->container = new \Pimple\Container();

		// Add wp_query to the container so it can be used later
		$this->container['wp_query'] = function () {
			global $wp_query;

			return $wp_query;
		};
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
	 * @return string
	 */
	public function formatControllerName($name)
	{
		return (string) \Stringy\Stringy::create($name)
			->removeRight('.php')
			->upperCamelize()
			->append('Controller');
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

		foreach ($templates as $template) {
			$controllers[] = $this->settings['controllerPath'] . $this->formatControllerName($template);
		}

		return $controllers;
	}

	/**
	 * Find a working controller, or throw an exception
	 *
	 * @return Controller
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
			// @todo Use a specific Exception
			throw new \Exception();
		}

		return $controller;
	}

	/**
	 * The heart of the operation — run the app
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
		$method = strtolower($_SERVER['REQUEST_METHOD'])

		// If the method `any` exists on our found controller, load that.
		// Otherwise, we load a method matching the request method (e.g.
		// get(), post(), etc.
		if (method_exists($controller, 'any')) {
			echo $controller->any($request, $response, $args);
		} else if (method_exists($controller, $method) {
			echo $controller->{$method}($request, $response, $args);
		} else {
			// @todo Use a specific Exception
			throw new \Exception();
		}

		exit;
	}
}
