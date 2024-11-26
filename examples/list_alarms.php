<?php
/**
 * PHP API usage example
 *
 * contributed by: Art of WiFi
 * description: An example basic PHP script to pull current alarms from the UniFi controller and output in JSON format,
 *              also demonstrates how to catch exceptions.
 */

use UniFi_API\Exceptions\CurlExtensionNotLoadedException;
use UniFi_API\Exceptions\CurlGeneralErrorException;
use UniFi_API\Exceptions\CurlTimeoutException;
use UniFi_API\Exceptions\InvalidBaseUrlException;
use UniFi_API\Exceptions\InvalidSiteNameException;
use UniFi_API\Exceptions\JsonDecodeException;
use UniFi_API\Exceptions\LoginFailedException;
use UniFi_API\Exceptions\LoginRequiredException;

/**
 * using the composer autoloader
 */
require_once 'vendor/autoload.php';

/**
 * Include the config file (place your credentials etc. there if not already present), see the config.template.php
 * file for an example.
 *
 * @var array $controlleruser
 * @var array $controllerpassword
 * @var array $controllerurl
 * @var array $controllerversion
 * @var array $debug
 */
require_once 'config.php';

/**
 * the site to use
 */
$site_id = '<enter your site id here>';

try {
    /**
     * initialize the UniFi API connection class and log in to the controller and do our thing
     */
    $unifi_connection = new UniFi_API\Client(
        $controlleruser,
        $controllerpassword,
        $controllerurl,
        $site_id,
        $controllerversion
    );

    $set_debug_mode = $unifi_connection->set_debug($debug);
    $login_results  = $unifi_connection->login();
    $data           = $unifi_connection->list_alarms();

    /**
     * provide feedback in json format
     */
    echo json_encode($data, JSON_PRETTY_PRINT);
} catch (CurlExtensionNotLoadedException $e) {
    echo 'CurlExtensionNotLoadedException: ' . $e->getMessage(). PHP_EOL;
} catch (InvalidBaseUrlException $e) {
    echo 'InvalidBaseUrlException: ' . $e->getMessage(). PHP_EOL;
} catch (InvalidSiteNameException $e) {
    echo 'InvalidSiteNameException: ' . $e->getMessage(). PHP_EOL;
} catch (JsonDecodeException $e) {
    echo 'JsonDecodeException: ' . $e->getMessage(). PHP_EOL;
    echo $unifi_connection->get_last_results_raw();
} catch (LoginRequiredException $e) {
    echo 'LoginRequiredException: ' . $e->getMessage(). PHP_EOL;
} catch (CurlGeneralErrorException $e) {
    echo 'CurlGeneralErrorException: ' . $e->getMessage(). PHP_EOL;
} catch (CurlTimeoutException $e) {
    echo 'CurlTimeoutException: ' . $e->getMessage(). PHP_EOL;
} catch (LoginFailedException $e) {
    echo 'LoginFailedException: ' . $e->getMessage(). PHP_EOL;
} catch (Exception $e) {
    echo 'General Exception: ' . $e->getMessage(). PHP_EOL;
}