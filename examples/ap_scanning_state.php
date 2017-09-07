<?php
/**
 * PHP API usage example
 *
 * contributed by: Art of WiFi
 * description: example basic PHP script to fetch an Access Point's scanning state/results
 */

/**
 * using the composer autoloader
 */
require_once('vendor/autoload.php');

/**
 * include the config file (place your credentials etc. there if not already present)
 * see the config.template.php file for an example
 */
require_once('config.php');

/**
 * site id and MAC address of AP to query
 */
$site_id = '<enter your site id here>';
$ap_mac  = '<enter MAC address of Access Point to check>';

/**
 * initialize the UniFi API connection class and log in to the controller and do our thing
 * spectrum_scan_state()
 */
$unifi_connection = new UniFi_API\Client($controlleruser, $controllerpassword, $controllerurl, $site_id, $controllerversion);
$set_debug_mode   = $unifi_connection->set_debug($debug);
$loginresults     = $unifi_connection->login();
$data             = $unifi_connection->spectrum_scan_state($ap_mac);

/**
 * provide feedback in json format
 */
echo json_encode($data, JSON_PRETTY_PRINT);