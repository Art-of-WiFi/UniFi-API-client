<?php
/**
 * PHP API usage example
 *
 * contributed by: Art of WiFi
 * description: example basic PHP script to pull stats for s epcific user/client device from the UniFi controller and output in json format
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
 * the site to use
 */
$site_id = '<enter your site id here>';

/**
 * MAC address of client to fetch stats for
 */
$mac = '<MAC address>';

/**
 * array of attributes to collect
 * valid attributes:
 * rx_bytes, tx_bytes, signal, rx_rate, tx_rate, rx_retries, tx_retries, rx_packets, tx_packets
 */
//$attribs = ['rx_bytes', 'tx_bytes', 'signal', 'rx_rate', 'tx_rate', 'rx_retries', 'tx_retries', 'rx_packets', 'tx_packets'];
$attribs = ['rx_bytes', 'tx_bytes'];

/**
 * initialize the UniFi API connection class and log in to the controller and do our thing
 */
$unifi_connection = new UniFi_API\Client($controlleruser, $controllerpassword, $controllerurl, $site_id, $controllerversion, true);
$set_debug_mode   = $unifi_connection->set_debug(false);
$loginresults     = $unifi_connection->login();
//$data             = $unifi_connection->stat_5minutes_user($mac, null, null, $attribs);
//$data             = $unifi_connection->stat_hourly_user($mac, null, null, $attribs);
$data             = $unifi_connection->stat_daily_user($mac, null, null, $attribs);

/**
 * provide feedback in json format
 */
echo json_encode($data, JSON_PRETTY_PRINT);