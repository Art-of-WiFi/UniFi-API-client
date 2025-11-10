<?php

namespace UniFi_API\Exceptions;

/**
 * Thrown when a MAC address was expected, but none was provided (empty/null).
 *
 * @package UniFi_Controller_API_Client_Class
 */
class MacAddressEmptyException extends UnifiApiException
{
    public function __construct()
    {
        parent::__construct('MAC address is empty.');
    }
}