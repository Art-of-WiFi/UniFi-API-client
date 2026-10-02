<?php
/**
 * PHP API usage example
 *
 * contributed by: Art of WiFi
 * description:    example to demonstrate API key authentication with a UniFi OS-based controller,
 *                 pulling site health metrics and outputting the results in json format
 *
 * note:           API key authentication is stateless — no login() or logout() calls are needed.
 *                 This is the required authentication method when your controller is part of a
 *                 UniFi Fabric.
 */
use UniFi_API\Exceptions\CurlExtensionNotLoadedException;
use UniFi_API\Exceptions\CurlGeneralErrorException;
use UniFi_API\Exceptions\CurlTimeoutException;
use UniFi_API\Exceptions\InvalidArgumentException;
use UniFi_API\Exceptions\InvalidBaseUrlException;
use UniFi_API\Exceptions\InvalidSiteNameException;
use UniFi_API\Exceptions\JsonDecodeException;

/**
 * using the composer autoloader
 */
require_once 'vendor/autoload.php';

/**
 * include the config file (place your credentials etc. there if not already present)
 * see the config.template.php file for an example
 */
require_once 'config.php';

/**
 * the short name of the site you wish to query
 */
$site_id = '<enter your site id here>';

/**
 * initialize the UniFi API connection class, set the API key and pull the requested data
 *
 * note: when using API key authentication, LoginFailedException and LoginRequiredException
 *       do not apply — you can omit those catch blocks
 */
try {
    $unifi_connection = new UniFi_API\Client('', '', $controllerurl, $site_id);
    $unifi_connection->set_api_key($controllerapikey);

    $set_debug_mode = $unifi_connection->set_debug($debug);
    $result         = $unifi_connection->list_health();

    /**
     * output the results in correct json formatting
     */
    header('Content-Type: application/json');
    echo json_encode($result, JSON_PRETTY_PRINT);
} catch (CurlExtensionNotLoadedException $e) {
    echo 'CurlExtensionNotLoadedException: ' . $e->getMessage() . PHP_EOL;
} catch (InvalidBaseUrlException $e) {
    echo 'InvalidBaseUrlException: ' . $e->getMessage() . PHP_EOL;
} catch (InvalidSiteNameException $e) {
    echo 'InvalidSiteNameException: ' . $e->getMessage() . PHP_EOL;
} catch (JsonDecodeException $e) {
    echo 'JsonDecodeException: ' . $e->getMessage() . PHP_EOL;
} catch (CurlGeneralErrorException $e) {
    echo 'CurlGeneralErrorException: ' . $e->getMessage() . PHP_EOL;
} catch (CurlTimeoutException $e) {
    echo 'CurlTimeoutException: ' . $e->getMessage() . PHP_EOL;
} catch (InvalidArgumentException $e) {
    echo 'InvalidArgumentException: ' . $e->getMessage() . PHP_EOL;
} catch (Exception $e) {
    /** catch any other Exceptions that might be thrown */
    echo 'General Exception: ' . $e->getMessage() . PHP_EOL;
}
