<?php
/**
 * PHP API usage example
 *
 * contributed by: Samuel Schnelly
 * description:    example basic PHP script to change VLAN on port
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
 * the site to use
 */
$site_id = '<enter your site id here>';

/**
 * initialize the UniFi API connection class and log in to the controller and do our thing
 */
$unifi_connection = new UniFi_API\Client($controlleruser, $controllerpassword, $controllerurl, $site_id, $controllerversion);
$set_debug_mode   = $unifi_connection->set_debug($debug);
$loginresults     = $unifi_connection->login();

/**
 * change VLAN on port
 */

$port = 1;
$vlan = 200;

$unifi_connection->set_port_vlan('<enter mac add here>', $port, $vlan);