<?php
/**
 * PHP API usage example
 *
 * contributed by: Art of WiFi
 * description: example basic PHP script to create a new PPSK for a WLAN on the UniFi controller
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

/**
 * The new PPSK details.
 */
$new_ppsk_password   = 'mysecretppsk'; // the password for the new PPSK, this password must be unique for the SSID, between 8-63 characters
$new_ppsk_network_id = 'zzzzzzzzzzzzzzzzzzzzz'; // id for the required VLAN, taken from the output of list_networkconf()
$new_ppsk_wlan_id    = 'xxxxxxxxxxxxxxxxxxxxx'; // id for the required WLAN, taken from the output of list_wlanconf()

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
        if ($wlan->_id === $new_ppsk_wlan_id) {
            $wlan_details = $wlan;

            break;
        }
    }

    if (empty($wlan_details)) {
        echo 'WLAN not found, exiting... Please check the $new_ppsk_wlan_id value 🤨' . PHP_EOL;

        exit;
    }

    /**
     * Create the new PPSK, then append it to the existing PPSKs array.
     */
    $new_ppsk = [
        'password'       => $new_ppsk_password,
        'networkconf_id' => $new_ppsk_network_id,
    ];

    $wlan_details->private_preshared_keys[] = $new_ppsk;

    $unifi_connection->set_wlansettings_base($new_ppsk_wlan_id, $wlan_details);

    $request_end_time = microtime(true);

    /**
     * Record end time.
     */
    $end_time = microtime(true);

    /**
     * Calculate and display the execution time.
     */
    $execution_time = $end_time - $start_time;

    echo 'The PPSK has been created successfully!👍' . PHP_EOL;

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