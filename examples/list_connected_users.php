<?php
/**
 * PHP API usage example
 *
 * contributed by: @gahujipo
 * description: example to pull connected users and their details from the UniFi controller and output the results
 *              in JSON format
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
$clients_array    = $unifi_connection->list_clients();

/**
 * output the results in JSON format
 */
header('Content-Type: application/json; charset=utf-8');
echo json_encode($clients_array);