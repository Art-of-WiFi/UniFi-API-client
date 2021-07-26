<?php
/**
 * PHP API usage example
 *
 * contributed by: Mike Siekkinen
 * description:    example basic PHP script to turn on LED lighting panel group
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
 * The LED panel group id assigned by controller you wish to control
 */
$led_panel_group_id = 'xxxxxxxx-xxxx-xxxx-xxxx-xxxxxxxxxxxx';



/**
 * initialize the UniFi API connection class and log in to the controller and do our thing
 */
$unifi_connection = new UniFi_API\LEDClient($controlleruser, $controllerpassword, $controllerurl, $site_id, $controllerversion, false);
$set_debug_mode   = $unifi_connection->set_debug(false);
$loginresults     = $unifi_connection->login();
$data = $unifi_connection->groupOn($led_panel_group_id);

/**
 * provide feedback in json format
 */
echo json_encode($data, JSON_PRETTY_PRINT);