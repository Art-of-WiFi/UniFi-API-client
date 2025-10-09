<?php

namespace UniFi_API\Exceptions;

use Exception;

class MacAddressInvalidException extends Exception
{
    public function __construct()
    {
        parent::__construct('MAC address is invalid.');
    }
}