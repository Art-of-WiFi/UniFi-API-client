<?php

namespace UniFi_API\Exceptions;

/**
 * Thrown when an invalid email address is provided to the client.
 *
 * @note This Exception is used for input validation where a properly formatted email is required.
 *
 * @package UniFi_Controller_API_Client_Class
 */
class EmailInvalidException extends UnifiApiException
{
    public function __construct()
    {
        parent::__construct('Invalid email address provided.');
    }
}
