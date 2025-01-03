<?php

namespace UniFi_API\Exceptions;

use Exception;

class CurlTimeoutException extends Exception
{
    /** @var mixed $_http_response_code */
    private $_http_response_code;

    /** @var mixed $_curl_getinfo_results */
    private $_curl_getinfo_results;

    public function __construct(string $message, $http_response_code, $curl_getinfo_results)
    {
        $this->_http_response_code   = $http_response_code;
        $this->_curl_getinfo_results = $curl_getinfo_results;

        parent::__construct($message);
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