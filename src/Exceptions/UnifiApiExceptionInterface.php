<?php

namespace UniFi_API\Exceptions;

use Throwable;

/**
 * Marker interface implemented by every exception thrown by the UniFi API client.
 *
 * @note Catch this interface to handle all exceptions originating from the client, including
 *       those that extend PHP's SPL exception classes rather than UnifiApiException
 *       (e.g., InvalidArgumentException).
 *
 * @package UniFi_Controller_API_Client_Class
 */
interface UnifiApiExceptionInterface extends Throwable
{
}
