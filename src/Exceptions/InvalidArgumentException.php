<?php

namespace UniFi_API\Exceptions;

/**
 * Thrown when a method is called with an invalid argument.
 *
 * @note Extends PHP's SPL \InvalidArgumentException for backwards compatibility with code that
 *       catches the SPL class, while also implementing UnifiApiExceptionInterface so it is
 *       caught together with all other client exceptions.
 *
 * @package UniFi_Controller_API_Client_Class
 */
class InvalidArgumentException extends \InvalidArgumentException implements UnifiApiExceptionInterface
{
}
