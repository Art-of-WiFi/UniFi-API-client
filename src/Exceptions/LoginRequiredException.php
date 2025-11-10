<?php

namespace UniFi_API\Exceptions;

/**
 * Thrown when a method that requires authentication is called before logging in.
 *
 * @package UniFi_Controller_API_Client_Class
 */
class LoginRequiredException extends UnifiApiException
{
    public function __construct()
    {
        parent::__construct('This method requires the API client to be logged in first.');
    }
}