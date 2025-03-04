<?php
/**
 * PHP API usage example
 *
 * contributed by: Art of WiFi
 * description: example basic PHP script to list all PPSKs for all WLANs in a specific UniFi site
 */

use UniFi_API\Exceptions\CurlExtensionNotLoadedException;
use UniFi_API\Exceptions\CurlGeneralErrorException;
use UniFi_API\Exceptions\CurlTimeoutException;
use UniFi_API\Exceptions\InvalidBaseUrlException;
use UniFi_API\Exceptions\InvalidSiteNameException;
use UniFi_API\Exceptions\JsonDecodeException;
use UniFi_API\Exceptions\LoginFailedException;
use UniFi_API\Exceptions\LoginRequiredException;

require 'vendor/autoload.php';

/**
 * Record start time.
 */
$start_time = microtime(true);

/**
 * Include the config file (place your credentials etc. there if not already present),
 * see the config.template.php file for an example.
 *
 * @var array $controller_user
 * @var array $controller_password
 * @var array $controller_url
 * @var array $controller_version
 * @var array $debug
 */
require_once 'config.php';

/**
 * The id of the site to use.
 */
$site_id = 'default';

try {
    /**
     * initialize the UniFi API connection class and log in to the controller and do our thing
     */
    $unifi_connection = new UniFi_API\Client(
        $controller_user,
        $controller_password,
        $controller_url,
        $site_id,
        $controller_version
    );

    $request_start_time = microtime(true);

    $set_debug_mode = $unifi_connection->set_debug($debug);
    $login_results  = $unifi_connection->login();
    $wlan_conf      = $unifi_connection->list_wlanconf();

    /**
     * Get the details for the WLAN the PPSK will be created for.
     */
    $wlan_details = [];

    foreach ($wlan_conf as $wlan) {
        /**
         * Skip this SSID if private_pre_shared_keys is not set or empty.
         */
        if (empty($wlan->private_preshared_keys)) {
            continue;
        }

        echo json_encode($wlan->private_preshared_keys, JSON_PRETTY_PRINT) . PHP_EOL;
    }

    $request_end_time = microtime(true);

    /**
     * Record end time.
     */
    $end_time = microtime(true);

    /**
     * Calculate and display the execution time.
     */
    $execution_time = $end_time - $start_time;

    echo 'Full execution time: ' . $execution_time . ' seconds' . PHP_EOL;
    echo 'Time to fetch, process and push data back: ' . ($request_end_time - $request_start_time) . ' seconds' . PHP_EOL;
} catch (CurlExtensionNotLoadedException $e) {
    echo 'CurlExtensionNotLoadedException: ' . $e->getMessage(). PHP_EOL;
} catch (InvalidBaseUrlException $e) {
    echo 'InvalidBaseUrlException: ' . $e->getMessage(). PHP_EOL;
} catch (InvalidSiteNameException $e) {
    echo 'InvalidSiteNameException: ' . $e->getMessage(). PHP_EOL;
} catch (JsonDecodeException $e) {
    echo 'JsonDecodeException: ' . $e->getMessage(). PHP_EOL;
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