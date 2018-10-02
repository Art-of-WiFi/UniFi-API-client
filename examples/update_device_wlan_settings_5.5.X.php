<?php
/**
 * PHP API usage example
 *
 * contributed by: Art of WiFi
 * description: example basic PHP script to update WLAN settings of a device when using a controller version 5.5.X or higher
 *              where set_ap_radiosettings() throws an error
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
 * the MAC address of the access point to modify
 */
$ap_mac = '<enter MAC address>';

/**
 * power level for 2.4GHz
 */
$ng_tx_power_mode = 'low';

/**
 * channel for 2.4GHz
 */
$ng_channel = 6;

/**
 * power level for 5GHz
 */
$na_tx_power_mode = 'medium';

/**
 * channel for 5GHz
 */
$na_channel = 44;

/**
 * initialize the UniFi API connection class and log in to the controller and do our thing
 */
$unifi_connection = new UniFi_API\Client($controlleruser, $controllerpassword, $controllerurl, $site_id, $controllerversion, false);
$set_debug_mode   = $unifi_connection->set_debug(false);
$loginresults     = $unifi_connection->login();
$data             = $unifi_connection->list_devices($ap_mac);
$radio_table      = $data[0]->radio_table;
$device_id        = $data[0]->device_id;

foreach ($radio_table as $radio) {
    if ($radio->radio === 'ng') {
        $radio->tx_power_mode = $ng_tx_power_mode;
        $radio->channel = $ng_channel;
    }

    if ($radio->radio === 'na') {
        $radio->tx_power_mode = $na_tx_power_mode;
        $radio->channel = $na_channel;
    }
}

$update_device = $unifi_connection->set_device_settings_base($device_id, ['radio_table' => $radio_table]);

if (!$update_device) {
    $error = $unifi_connection->get_last_results_raw();
    echo json_encode($error, JSON_PRETTY_PRINT);
}

/**
 * provide feedback in json format
 */
echo json_encode($update_device, JSON_PRETTY_PRINT);