<?php

namespace UniFi_API\Exceptions;

use Exception;

class LoginRequiredException extends Exception
{
    public function __construct()
    {
        parent::__construct('This method requires the API client to be logged in first.');
    }
}