<?php
/**
 * PHP API usage example
 *
 * contributed by: Art of WiFi
 * description: example basic PHP script to execute a custom API request using the
 *              custom_api_request() function/method
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
 * The site to authorize the device with
 * https://github.com/Art-of-WiFi/UniFi-API-client#important-notes
 */
$site_id = '<enter your site id here>';

/**
 * parameters
 */
$url          = '/api/s/' . $site_id . '/stat/fwupdate/latest-version';
$request_type = 'GET';
$payload      = null;
$return       = 'array';

/**
 * initialize the UniFi API connection class and log in to the controller and do our thing
 */
$unifi_connection = new UniFi_API\Client($controlleruser, $controllerpassword, $controllerurl, $site_id, $controllerversion);
$set_debug_mode   = $unifi_connection->set_debug($debug);
$loginresults     = $unifi_connection->login();
$results          = $unifi_connection->custom_api_request($url, $request_type, $payload, $return);

/**
 * provide feedback in JSON format or as PHP Object
 */
echo json_encode($results, JSON_PRETTY_PRINT);
//print_r($results);
