<?php

namespace UniFi_API\Exceptions;

/**
 * Thrown when a cURL request times out.
 *
 * @note The HTTP response code is available through getHttpResponseCode() (inherited from UnifiApiException).
 *
 * @package UniFi_Controller_API_Client_Class
 */
class CurlTimeoutException extends UnifiApiException
{
    /** @var mixed $_curl_getinfo_results */
    private $_curl_getinfo_results;

    /**
     * @param string $message              human-readable message
     * @param mixed  $http_response_code   HTTP response code of the failed request, if any
     * @param mixed  $curl_getinfo_results results of curl_getinfo() for the failed request
     */
    public function __construct(string $message, $http_response_code, $curl_getinfo_results)
    {
        $this->_curl_getinfo_results = $curl_getinfo_results;

        parent::__construct($message, (int)$http_response_code);
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
