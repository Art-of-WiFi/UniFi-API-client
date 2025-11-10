<?php

namespace UniFi_API\Exceptions;

/**
 * Thrown when the target host is not a UniFi OS console.
 *
 * @package UniFi_Controller_API_Client_Class
 */
class NotAUnifiOsConsoleException extends UnifiApiException
{
    public function __construct()
    {
        parent::__construct('This is not a UniFi OS console.');
    }
}
