<?php
/**
 * PHP API usage example
 *
 * contributed by: Art of WiFi
 * description: example to pull site health metrics from the Unifi controller and output the results
 *              in json format
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
 * the short name of the site you wish to query
 */
$site_id = '<enter your site id here>';

/**
 * initialize the UniFi API connection class and log in to the controller and pull the requested data
 */

$unifi_connection = new UniFi_API\Client($controlleruser, $controllerpassword, $controllerurl, $site_id, $controllerversion);
$set_debug_mode   = $unifi_connection->set_debug($debug);
$loginresults     = $unifi_connection->login();
$result           = $unifi_connection->list_health();

/**
 * output the results in correct json formatting
 */
header('Content-Type: application/json');
echo (json_encode($result, JSON_PRETTY_PRINT));