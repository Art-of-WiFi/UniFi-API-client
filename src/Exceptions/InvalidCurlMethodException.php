<?php

namespace UniFi_API\Exceptions;

/**
 * Thrown when an unsupported or invalid cURL method is requested by the client.
 *
 * @package UniFi_Controller_API_Client_Class
 */
class InvalidCurlMethodException extends UnifiApiException
{
    public function __construct()
    {
        parent::__construct('Invalid cURL method provided.');
    }
}