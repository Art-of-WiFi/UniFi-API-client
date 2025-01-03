<?php

namespace UniFi_API\Exceptions;

use Exception;

class InvalidBaseUrlException extends Exception
{
    public function __construct()
    {
        parent::__construct('The base URL provided is invalid.');
    }
}