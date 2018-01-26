<?php

namespace Tomodomo;

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

		// Loop through the possible controllers
		foreach ($controllers as $controller) {
			// Check if it exists; instantiate if so
			if (class_exists($controller)) {
				$controller = new $controller($this->container);

				break;
			}
		}

		// Sanity check on the controller
		if (!is_object($controller)) {
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
		$controller = $this->getController();

		// If the method `any` exists on our found controller, load that.
		// Otherwise, we load a method matching the request method (e.g.
		// get(), post(), etc.
		//
		// @TODO This is also where we can pass in a PSR-7 style request
		// object, if we want.
		if (method_exists($controller, 'any')) {
			echo $controller->any();
		} else {
			echo $controller->{strtolower($_SERVER['REQUEST_METHOD'])}();
		}

		exit;
	}
}
