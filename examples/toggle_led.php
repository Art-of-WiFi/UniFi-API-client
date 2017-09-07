<?php
/**
 * PHP API usage example
 *
 * contributed by: Art of WiFi
 * description: example to toggle the locate function (flashing LED) on an Access Point and
 *              output the response in json format
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
 * site id to use
 */
$site_id = '<enter your site id here>';

/**
 * other specific variables to be used
 */
$mac = '<enter MAC address of your AP here>';

/**
 * initialize the UniFi API connection class and log in to the controller to do our thing
 */
$unifi_connection = new UniFi_API\Client($controlleruser, $controllerpassword, $controllerurl, $site_id, $controllerversion); // initialize the class instance
$set_debug_mode   = $unifi_connection->set_debug($debug);
$loginresults     = $unifi_connection->login();

/**
 * using the new method/function
 */
$data = $unifi_connection->locate_ap($mac, true); // uncomment to switch locating on
//$data = $unifi_connection->locate_ap($mac, false); // uncomment to switch locating off (choose either of these two lines!)

if ($data) {
    /**
     * provide feedback in json format
     */
    echo json_encode($data, JSON_PRETTY_PRINT);
} else {
    /**
     * method returned false so we display the raw results in json format
     */
    echo '<pre>';
    print_r($unifi_connection->get_last_results_raw(true));
    echo '</pre>';
}