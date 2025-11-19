<?php

namespace UniFi_API\Exceptions;

/**
 * Thrown when the required PHP cURL extension is not loaded in the runtime.
 *
 * @note Consumers can catch this to provide an installation hint or to disable any
 *       functionality that requires cURL.
 *
 * @package UniFi_Controller_API_Client_Class
 */
class CurlExtensionNotLoadedException extends UnifiApiException
{
    public function __construct()
    {
        parent::__construct('The PHP curl extension is not loaded. Please correct this before proceeding!');
    }
}