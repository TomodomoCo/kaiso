<?php
/**
 * This is an example of a theme index.php file.
 *
 * You may wish to copy this theme directory to your
 * wp-content/themes directory, or start your own version.
 *
 * (If you've ever worked with minimalist frameworks like
 * Slim, this should be familiar to you!
 */

use \Tomodomo\Kaiso as App;

// App settings. So far you only need to provide a controllerPath
$settings = [
	'controllerPath' => '\\MyApp\\Controllers\\',
];

// Instantiate the app with our settings
$app = new App($settings);

// An example of adding Twig to your container
$app->container['twig'] = function () {
	$loader = new Twig_Loader_Filesystem(ABSPATH . '../../app/views');

	return new Twig_Environment($loader);
};

// Run the app!
$app->run();
