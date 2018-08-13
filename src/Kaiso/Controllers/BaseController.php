<?php

namespace Tomodomo\Kaiso\Controllers;

class BaseController
{
    /**
     * The Pimple container for this controller
     *
     * @var \Pimple\Container
     */
    public $container;

    /**
     * Instantiate the controller with a Container
     *
     * @param \Pimple\Container $container
     *
     * @return void
     */
    public function __construct(\Pimple\Container $container)
    {
        $this->container = $container;
    }
}
