<?php

namespace UniFi_API\Exceptions;

use Exception;

class MacAddressEmptyException extends Exception
{
    public function __construct()
    {
        parent::__construct('MAC address is empty.');
    }
}