<?php

namespace UniFi_API\Exceptions;

use Exception;

class InvalidCurlMethodException extends Exception
{
    public function __construct()
    {
        parent::__construct('Invalid cURL method provided.');
    }
}