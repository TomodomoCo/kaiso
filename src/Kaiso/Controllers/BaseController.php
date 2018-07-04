<?php

namespace TomodomoCo\Kaiso\Controllers;

class BaseController {
	/**
	 * @var Pimple\Container
	 */
	public $container;

	/**
	 * @param \Pimple\Container $container
	 *
	 * @return void
	 */
	public function __construct($container) {
		$this->container = $container;
	}
}
