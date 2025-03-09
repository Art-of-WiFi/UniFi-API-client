<?php

namespace UniFi_API\Exceptions;

use Exception;

class NotAUnifiOsConsoleException extends Exception
{
    public function __construct()
    {
        parent::__construct('This is not a UniFi OS console.');
    }
}
