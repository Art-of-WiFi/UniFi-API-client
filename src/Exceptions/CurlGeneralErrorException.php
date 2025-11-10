<?php

namespace UniFi_API\Exceptions;

/**
 * Thrown when a general cURL error occurs while calling the UniFi API.
 *
 * @property-read mixed $httpResponseCode HTTP response code if available
 * @property-read mixed $curlGetinfoResults Results from curl_getinfo() if available
 *
 * @package UniFi_Controller_API_Client_Class
 */
class CurlGeneralErrorException extends UnifiApiException
{
    /** @var mixed $_http_response_code */
    private $_http_response_code;

    /** @var mixed $_curl_getinfo_results */
    private $_curl_getinfo_results;

    public function __construct(string $message, $http_response_code, $_curl_getinfo_results)
    {
        $this->_http_response_code   = $http_response_code;
        $this->_curl_getinfo_results = $_curl_getinfo_results;

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

    /**
     * Get the cURL curl_getinfo results.
     *
     * @return mixed
     */
    public function getCurlGetinfoResults()
    {
        return $this->_curl_getinfo_results;
    }
}