<?php
/**
 * PHP API usage example
 *
 * contributed by: Art of WiFi
 * description: example basic PHP script to remove a PPSK from a specific UniFi site
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

$total_removals = 0;

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
 * The password value of the PPSK to remove.
 */
$ppsk_to_remove = 'mysecretppsk';

try {
    /**
     * Initialize the UniFi API connection class and log in to the controller and do our thing.
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

    foreach ($wlan_conf as $wlan) {
        /**
         * Skip this SSID if the private_pre_shared_keys array is not set or empty.
         */
        if (empty($wlan->private_preshared_keys)) {
            continue;
        }

        $removals = 0;

        foreach ($wlan->private_preshared_keys as $ppsk) {
            if ($ppsk->password === $ppsk_to_remove) {
                echo 'Removing PPSK with password: "' . $ppsk_to_remove . '"' . PHP_EOL;

                /**
                 * Remove the PPSK from the private_preshared_keys array.
                 */
                $wlan->private_preshared_keys = array_values(array_filter($wlan->private_preshared_keys, function ($value) use ($ppsk_to_remove) {
                    return $value->password !== $ppsk_to_remove;
                }));

                $removals++;
            }
        }

        /**
         * Push the updated WLAN configuration back to the controller if we removed one or more PPSKs.
         */
        if ($removals > 0) {
            echo 'Pushing updated WLAN configuration back to the controller...' . PHP_EOL;
            $unifi_connection->set_wlansettings_base($wlan->_id, $wlan);
            $total_removals += $removals;
        }
    }

    $request_end_time = microtime(true);

    /**
     * Record end time.
     */
    $end_time = microtime(true);

    /**
     * Calculate the execution time.
     */
    $execution_time = $end_time - $start_time;

    if ($total_removals === 0) {
        echo 'No PPSKs were removed, exiting...' . PHP_EOL;

        exit;
    }

    echo 'Total PPSKs removed: ' . $total_removals . PHP_EOL;

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