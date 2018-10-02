<?php
/**
 * PHP API usage example
 *
 * contributed by: Art of WiFi
 * description: example basic PHP script to update the port settings of an AC-IW device
 *              FYI: the AC-IW device has three ports, one for the wired uplink and two with external connectors
 * note: requires controller version 5.5.X or higher (to be verified)
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
 * the site to use to log in to the controller
 */
$site_id = '<short site name of a site the credentials used have access to>';

/**
 * the MAC address of the AC-IW device to re-configure
 */
$device_mac = '<enter MAC address>';

/**
 * port configuration id to apply to port #1 of the AC-IW device
 * NOTE: port configurations are available through list_portconf()
 */
$port_conf_id_port_1 = '<_id of port configuration to apply to port #1>';

/**
 * port configuration id to apply to port #2 of the AC-IW device
 * NOTE: port configurations are available through list_portconf()
 */
$port_conf_id_port_2 = '<_id of port configuration to apply to port #2>';

/**
 * prepare the payload to pass on to the API endpoint
 */
$new_ports_config = [
    'port_overrides' => [
        [
            'port_idx' => 1,
            'portconf_id' => $port_conf_id_port_1
        ],
        [
            'port_idx' => 2,
            'portconf_id' => $port_conf_id_port_2
        ]
    ]
];

/**
 * initialize the UniFi API connection class and log in to the controller and do our thing
 */
$unifi_connection = new UniFi_API\Client($controlleruser, $controllerpassword, $controllerurl, $site_id, $controllerversion, false);
$set_debug_mode   = $unifi_connection->set_debug(false);
$loginresults     = $unifi_connection->login();
$data             = $unifi_connection->list_devices($device_mac);
$device_id        = $data[0]->device_id;
$update_device    = $unifi_connection->set_device_settings_base($device_id, $new_ports_config);

if (!$update_device) {
    $error = $unifi_connection->get_last_results_raw();
    echo json_encode($error, JSON_PRETTY_PRINT);
}

/**
 * provide feedback in json format
 */
echo json_encode($update_device, JSON_PRETTY_PRINT);