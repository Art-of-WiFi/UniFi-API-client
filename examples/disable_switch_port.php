<?php
/**
 * PHP API usage example
 *
 * contributed by: Art of WiFi
 * description:    example PHP script to disable/enable the port of a UniFi switch
 * note:           Requires controller version 5.5.X or higher. This example assumes an override alreay exists for the desired port.
 *                 To create a new port override simply append one (similar in structure to $updated_override) as needed to the
 *                 $existing_overrides array
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
$site_id = '<enter your site id here>';

/**
 * the MAC address of the UniFi switch to re-configure
 */
$device_mac = '<enter MAC address>';

/**
 * index of port to modify/add
 */
$port_idx = 24;

/**
 * port configuration id to apply when enabling/disabling the port
 *
 * NOTE:
 * port configurations are available through list_portconf()
 */
$port_conf_id = '<enter _id value of desired port configuration>';

/**
 * initialize the UniFi API connection class and log in to the controller and do our thing
 */
$unifi_connection   = new UniFi_API\Client($controlleruser, $controllerpassword, $controllerurl, $site_id, $controllerversion, false);
$set_debug_mode     = $unifi_connection->set_debug($debug);
$loginresults       = $unifi_connection->login();
$data               = $unifi_connection->list_devices($device_mac);
$device_id          = $data[0]->device_id;
$existing_overrides = $data[0]->port_overrides;

foreach ($existing_overrides as $key => $value) {
    if (!empty($value->port_idx) && $value->port_idx === $port_idx) {
        $updated_override = [
            'portconf_id' => $port_conf_id,
            'port_idx'    => $port_idx,
            'poe_mode'    => $value->poe_mode,
            'name'        => 'Your-port-name',
        ];

        $existing_overrides[$key] = $updated_override;
    }
}

$payload = [
    'port_overrides' => $existing_overrides
];

$update_device = $unifi_connection->set_device_settings_base($device_id, $payload);

/**
 * provide feedback in json format
 */
echo json_encode($update_device, JSON_PRETTY_PRINT);