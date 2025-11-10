<?php

namespace UniFi_API\Exceptions;

/**
 * Thrown when a provided MAC address does not match expected formatting or
 * validation rules.
 *
 * @package UniFi_Controller_API_Client_Class
 */
class MacAddressInvalidException extends UnifiApiException
{
    public function __construct()
    {
        parent::__construct('MAC address is invalid.');
    }
}