<?php
/**
 * PHP API usage example
 *
 * contributed by: @4oo4
 * description: example script to upgrade device firmware (can be scheduled with systemd/cron)
 *              to the most current version
 */
require_once('vendor/autoload.php');
require_once('config.php');

/**
 * site id of the AP to update
 * https://github.com/Art-of-WiFi/UniFi-API-client#important-notes
 */
$site_id = '<enter your site id here>';

/**
 * device MAC address formatted with colons, e.g. 'de:ad:be:ef:01:23'
 */
$device_mac = '<enter MAC address of device to update>';

/**
 * initialize the UniFi API connection class, log in to the controller
 * (this example assumes you have already assigned the correct values in config.php to the variables used)
 */
$unifi_connection = new UniFi_API\Client($controlleruser, $controllerpassword, $controllerurl, $site_id, $controllerversion, false);
$login            = $unifi_connection->login();

/**
 * Run the actual upgrade
 */
$results = $unifi_connection->upgrade_device($device_mac);

/**
 * provide feedback in json format from $response given by upgrade_device();
 */
echo json_encode($results, JSON_PRETTY_PRINT);