<?php

namespace UniFi_API\Exceptions;

use Exception;
use Throwable;

/**
 * Base exception for the UniFi API client.
 *
 * @note All custom exceptions in this library extend this class so consumers can
 *       catch a single type (\UniFi_API\Exceptions\UnifiApiException) when they
 *       want to handle all client errors uniformly.
 *
 * @package UniFi_Controller_API_Client_Class
 */
class UnifiApiException extends Exception
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
}
