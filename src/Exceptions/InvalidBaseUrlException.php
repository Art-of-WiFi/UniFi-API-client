<?php

namespace UniFi_API\Exceptions;

/**
 * Thrown when the provided base URL for the UniFi controller is invalid.
 *
 * @package UniFi_Controller_API_Client_Class
 */
class InvalidBaseUrlException extends UnifiApiException
{
    public function __construct()
    {
        parent::__construct('The base URL provided is invalid.');
    }
}