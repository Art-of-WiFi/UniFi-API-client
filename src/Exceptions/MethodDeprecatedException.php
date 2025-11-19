<?php

namespace UniFi_API\Exceptions;

/**
 * Thrown when a library method or API endpoint has been deprecated and should
 * no longer be used.
 *
 * @note Consumers can catch this to provide migration guidance or suppress warnings for legacy callers.
 *
 * @package UniFi_Controller_API_Client_Class
 */
class MethodDeprecatedException extends UnifiApiException
{
    // Intentionally empty - serves as a distinct exception type.
}