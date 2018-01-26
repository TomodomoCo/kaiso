<?php

namespace MyApp\Controllers;

use \Tomodomo\Kaiso\Controllers\BaseController;

/**
 * IndexController is for demo purposes.
 */
class IndexController extends BaseController {

	/**
	 * Handle a GET request when this controller's template path is called
	 *
	 * @return string
	 */
	public function get() {
		return $this->container['twig']->render('index.twig');
	}

}
