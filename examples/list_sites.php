<?php
/**
 * PHP API usage example
 *
 * contributed by: Art of WiFi
 * description:    example basic PHP script to list all site on the controller that are
 *                 available to the admin account defined in config.php
 */

/**
 * using the composer autoloader
 */
require_once 'vendor/autoload.php';

/**
 * include the config file (place your credentials etc. there if not already present)
 * see the config.template.php file for an example
 */
require_once 'config.php';

/**
 * we use the default site in the initial connection
 */
$site_id = 'default';

/**
 * initialize the UniFi API connection class and log in to the controller and do our thing
 */
$unifi_connection = new UniFi_API\Client($controlleruser, $controllerpassword, $controllerurl, $site_id, $controllerversion);
$set_debug_mode   = $unifi_connection->set_debug($debug);
$loginresults     = $unifi_connection->login();
$data             = $unifi_connection->list_sites();

/**
 * we can render the full results in json format
 */
//echo json_encode($data, JSON_PRETTY_PRINT);

/**
 * or we print each site name and site id
 */
foreach ($data as $site) {
    echo 'Site name: ' . $site->desc . ', site id: ' . $site->name . PHP_EOL;
}

echo PHP_EOL;