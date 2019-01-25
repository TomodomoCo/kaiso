<?php

namespace Tomodomo\Kaiso\Exceptions;

class BaseException extends \Exception
{
    /**
     * An array of data about the Exception
     *
     * @var array
     */
    private $_data = [];

    /**
     * Create the Exception, optionally with data
     *
     * @param string $message
     * @param array $data
     *
     * @return void
     */
    public function __construct(string $message, array $data = [])
    {
        // Add the message to the Exception
        parent::__construct($message);

        // Save the data
        $this->_data = $data;

        return;
    }

    /**
     * Get the data about the exception
     *
     * @return array
     */
    public function getData()
    {
        return $this->_data;
    }
}
