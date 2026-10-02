<?php

namespace UniFi_API\Exceptions;

/**
 * Thrown when the controller accepts the request but returns an error in its response body.
 *
 * @note Covers the three response formats the client understands:
 *       - classic API: `meta.rc === 'error'`, error code in `meta.msg` (e.g., 'api.err.NoSiteContext')
 *       - v2 API: `errorCode` and `message` properties
 *       - UniFi OS endpoints: `code` and `message` properties
 *
 * @package UniFi_Controller_API_Client_Class
 */
class ControllerErrorException extends UnifiApiException
{
    /** @var string|int|null $_api_error_code */
    private $_api_error_code;

    /** @var mixed $_response */
    private $_response;

    /**
     * @param string          $message        human-readable message
     * @param string|int|null $api_error_code error code as returned by the controller, e.g. 'api.err.Invalid'
     * @param mixed           $response       the decoded response object as returned by the controller
     */
    public function __construct(string $message, $api_error_code = null, $response = null)
    {
        $this->_api_error_code = $api_error_code;
        $this->_response       = $response;

        parent::__construct($message);
    }

    /**
     * Get the error code as returned by the controller.
     *
     * @return string|int|null e.g. 'api.err.NoSiteContext' for the classic API, an integer or string for v2/UniFi OS
     */
    public function getApiErrorCode()
    {
        return $this->_api_error_code;
    }

    /**
     * Get the full decoded response object that contained the error.
     *
     * @return mixed
     */
    public function getResponse()
    {
        return $this->_response;
    }
}
