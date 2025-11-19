<?php

namespace UniFi_API\Exceptions;

/**
 * Thrown when a provided short site name is invalid or cannot be used by the client.
 *
 * @note This Exception is thrown when the short site name contains illegal characters or when
 *       the name does not correspond to any known site on the controller.
 *
 * @package UniFi_Controller_API_Client_Class
 */
class InvalidSiteNameException extends UnifiApiException
{
    public function __construct()
    {
        parent::__construct('Invalid site name provided.');
    }
}