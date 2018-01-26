<?php

namespace MyApp\Controllers;

use \Tomodomo\Kaiso\Controllers\BaseController;

/**
 * SingleController is for demo purposes.
 */
class SingleController extends BaseController {

	/**
	 * Handle a GET request when this controller's template path is called
	 *
	 * @return string
	 */
	public function get() {
		$context = [
			'posts' => $this->container['wp_query']->posts,
		];

		return $this->container['twig']->render('single.twig', $context);
	}

}
