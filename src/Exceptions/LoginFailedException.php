<?php

namespace UniFi_API\Exceptions;

/**
 * Thrown when the client fails to authenticate with the UniFi controller.
 *
 * @note This can indicate invalid credentials, connectivity problems, or a change
 *       in the controller's authentication mechanism (e.g., MFA).
 *
 * @note The HTTP response code is available through getHttpResponseCode() (inherited from UnifiApiException).
 *
 * @package UniFi_Controller_API_Client_Class
 */
class LoginFailedException extends UnifiApiException
{
    /**
     * @param string $message            human-readable message
     * @param mixed  $http_response_code HTTP response code of the failed login request
     */
    public function __construct(string $message, $http_response_code)
    {
        parent::__construct($message, (int)$http_response_code);
    }
}
