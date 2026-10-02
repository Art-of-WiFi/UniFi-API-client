<?php

namespace UniFi_API\Exceptions;

use Exception;
use Throwable;

/**
 * Base exception for the UniFi API client.
 *
 * @note Nearly all custom exceptions in this library extend this class so consumers can
 *       catch a single type (\UniFi_API\Exceptions\UnifiApiException) when they
 *       want to handle all client errors uniformly. The sole exception is
 *       \UniFi_API\Exceptions\InvalidArgumentException which extends PHP's SPL
 *       \InvalidArgumentException; catch UnifiApiExceptionInterface to cover that as well.
 *
 * @package UniFi_Controller_API_Client_Class
 */
class UnifiApiException extends Exception implements UnifiApiExceptionInterface
{
    /**
     * UnifiApiException constructor.
     *
     * @param string         $message  Human-readable message describing the error.
     * @param int            $code     Optional error code.
     * @param Throwable|null $previous Optional previous exception for chaining.
     */
    public function __construct(string $message = 'An error occurred in the UniFi API client.', int $code = 0, ?Throwable $previous = null)
    {
        parent::__construct($message, $code, $previous);
    }

    /**
     * Get the HTTP response code associated with this exception, if any.
     *
     * @note For exceptions raised by cURL/HTTP operations this is the HTTP status code; for all other
     *       exceptions it is the generic exception code (usually 0).
     *
     * @return int
     */
    public function getHttpResponseCode()
    {
        return $this->getCode();
    }
}
