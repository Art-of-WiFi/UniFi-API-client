<?php

namespace UniFi_API\Exceptions;

use Exception;

class EmailInvalidException extends Exception
{
    public function __construct()
    {
        parent::__construct('Invalid email address provided.');
    }
}
