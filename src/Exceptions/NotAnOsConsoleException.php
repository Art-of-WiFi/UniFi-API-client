<?php

namespace UniFi_API\Exceptions;

use Exception;

class NotAnOsConsoleException extends Exception
{
    public function __construct()
    {
        parent::__construct('This console is not an UniFi OS console.');
    }
}
