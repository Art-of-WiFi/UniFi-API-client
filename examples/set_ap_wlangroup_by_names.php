<?php
/**
 * PHP API usage example
 *
 * contributed by: Art of WiFi
 * description: example to change the wlan group of APs by the name of the AP and the name of the group
 */

/**
 * using the composer autoloader
 */
require_once('vendor/autoload.php');

/**
 * include the config file (place your credentials etc there if not already present)
 * see the config.template.php file for an example
 */
require_once('config.php');

/**
 * the short name of the site which you wish to query
 */
$site_id = '<enter your site id here>';

/**
 * initialize the UniFi API connection class and log in to the controller and pull the requested data
 */
$unifi_connection = new UniFi_API\Client($controlleruser, $controllerpassword, $controllerurl, $site_id, $controllerversion);
$set_debug_mode   = $unifi_connection->set_debug($debug);
$loginresults     = $unifi_connection->login();
$result           = $unifi_connection->set_ap_wlangroups_by_device_name('<device_name>','<wlan group name e.g. Default>');

/**
 * output the results in JSON format
 */
header('Content-Type: application/json; charset=utf-8');

echo json_encode($result);