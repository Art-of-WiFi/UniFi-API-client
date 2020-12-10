<?php
/**
 * PHP API usage example
 *
 * contributed by: Art of WiFi
 * description:    example basic PHP script to toggle power of an outlet on the UniFi SmartPower PDU Pro,
 *                 last tested with UniFi controller version 6.1.19
 */
require 'vendor/autoload.php';

/**
 * include the config file (place your credentials etc. there if not already present)
 * see the config.template.php file for an example
 */
require_once('config.php');

/**
 * the site to use
 */
$site_id = 'default';

/**
 * MAC of UniFi SmartPower PDU Pro to work with
 */
$pdu_mac = '<MAC ADDRESS of PDU>';

/**
 * index value of the outlet to modify
 */
$outlet_idx = 20;

/**
 * new values for relay_state (enable/disable Power) and cycle_enabled (disable/enable Modem Power Cycle) for the above outlet,
 * values must be boolean (true/false)
 *
 * NOTES:
 * - here you can choose to also change the name of the outlet
 * - outlet overrides are structured like this:
 *    {
 *        "index": 1,
 *        "name": "USB Outlet 1",
 *        "cycle_enabled": false,
 *        "relay_state": true
 *    }
 */
$new_relay_state   = true;
$new_cycle_enabled = false;

/**
 * initialize the UniFi API connection class and log in to the controller and do our thing
 */
$unifi_connection = new UniFi_API\Client($controlleruser, $controllerpassword, $controllerurl, $site_id, $controllerversion);
$set_debug_mode   = $unifi_connection->set_debug($debug);
$loginresults     = $unifi_connection->login();
if ($loginresults) {
    $pdu_details = $unifi_connection->list_devices($pdu_mac);

    /**
     * change the model below from USPPDUP to UP1 when using a USP-Plug (thanks to @thesohoguy for contributing this)
     */
    if (!empty($pdu_details) && property_exists($pdu_details[0], 'model') && $pdu_details[0]->model === 'USPPDUP' && property_exists($pdu_details[0], 'outlet_overrides')) {
        $device_id        = $pdu_details[0]->_id;
        $outlet_overrides = $pdu_details[0]->outlet_overrides;

        foreach ($outlet_overrides as $key => $value) {
            if ($value->index === $outlet_idx) {
                $outlet_overrides[$key]->relay_state   = $new_relay_state;
                $outlet_overrides[$key]->cycle_enabled = $new_cycle_enabled;
            }
        }

        $pdu_update = $unifi_connection->set_device_settings_base($device_id, ['outlet_overrides' => $outlet_overrides]);

        /**
         * provide feedback in json format
         */
        echo 'results:' . PHP_EOL . PHP_EOL;
        echo json_encode($pdu_update, JSON_PRETTY_PRINT);
        echo PHP_EOL;
    } else {
        echo 'not a PDU device?';
        echo PHP_EOL;
    }
} else {
    echo 'we encountered a login error!';
    echo PHP_EOL;
}