<?php

namespace UniFi_API\Exceptions;

/**
 * Thrown when the client fails to authenticate with the UniFi controller.
 *
 * @note This can indicate invalid credentials, connectivity problems, or a change
 *       in the controller's authentication mechanism (e.g., MFA).
 *
 * @property-read mixed $httpResponseCode HTTP response code if available
 *
 * @package UniFi_Controller_API_Client_Class
 */
class LoginFailedException extends UnifiApiException
{
    /** @var mixed $_http_response_code */
    private $_http_response_code;

    public function __construct(string $message, $http_response_code)
    {
        $this->_http_response_code   = $http_response_code;

        parent::__construct($message, $http_response_code);
    }

    /**
     * Get the HTTP response code.
     *
     * @return mixed
     */
    public function getHttpResponseCode()
    {
        return $this->_http_response_code;
    }
}