<?php
/**
 * PHP API usage example
 *
 * contributed by: Art of WiFi
 * description: example basic PHP script to change the WPA2 password/PSK of a WLAN, returns true on success
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
 * The site to which the WLAN you want to modify belongs
 */
$site_id = '<enter your (short) site name here>';

/**
 * the id of the WLAN you wish to modify
 */
$wlan_id = '<the value of _id for the WLAN you wish to change>';

/**
 * the new WPA2 password/PSK to apply to the above WLAN
 */
$new_password = '<new password goes here>';

/**
 * initialize the UniFi API connection class and log in to the controller
 */
$unifi_connection = new UniFi_API\Client($controlleruser, $controllerpassword, $controllerurl, $site_id, $controllerversion);
$set_debug_mode   = $unifi_connection->set_debug($debug);
$loginresults     = $unifi_connection->login();
$results          = $unifi_connection->set_wlansettings($wlan_id, $new_password);

/**
 * provide feedback in json format
 */
echo json_encode($results, JSON_PRETTY_PRINT);
