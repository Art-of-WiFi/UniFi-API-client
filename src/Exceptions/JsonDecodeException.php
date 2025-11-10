<?php

namespace UniFi_API\Exceptions;

/**
 * Thrown when the library fails to decode JSON returned by the controller.
 *
 * @package UniFi_Controller_API_Client_Class
 */
class JsonDecodeException extends UnifiApiException
{
    // Intentionally empty - represents JSON decoding failures.
}