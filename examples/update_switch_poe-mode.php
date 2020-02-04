<?php
/**
 * PHP API usage example to turn the PoE of the selected switch ports to "off" or "auto"
 *
 * contributed by: @Kaltt
 * description:    A use case for this script is to turn off the PoE of the port where a camera is connected in order to turn off the camera
 *
 * usage:          If the file is called via a web URL, it should be called like: update_switch_poe-mode.php?poe_mode=off
 *                 If the file is called via the command line, it should be called like: php update_switch_poe-mode.php off
 *                 The values can be "off" or "auto"
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
 * $lanports is an array that defines which ports should be changed
 */
$lanports = [6];

/**
 * This is the function that reads out the current port configuration and changes the value for the poe_mode for the ports defined in $lanports
 */
function update_ports($running_config, $ports, $poe_mode){
    /**
     * Update already non-default ports
     */
    $running_config_count = count($running_config);
    for($i = 0; $i < $running_config_count; $i++){
        if(in_array($running_config[$i]->port_idx, $ports)){
            $running_config[$i]->poe_mode = $poe_mode;
            unset($ports[array_search($running_config[$i]->port_idx, $ports)]);
        }
    }

    $add_conf = [];
    foreach($ports as $port){
        $add_conf[] = [
            'port_idx' => $port,
            'poe_mode' => $poe_mode
        ];
    }

    return array_merge($running_config, $add_conf);
}

$unifi_connection = new UniFi_API\Client($controlleruser, $controllerpassword, $controllerurl, $site_id, $controllerversion, false);
$set_debug_mode   = $unifi_connection->set_debug(false);
$loginresults     = $unifi_connection->login();
$data             = $unifi_connection->list_devices($device_mac);
$device_id        = $data[0]->device_id;
$current_conf     = $data[0]->port_overrides;

/**
 * This reads in the values provided via URL or in the command line, if nothing is set than it will poe_mode will be set to "auto"
 */
if (isset($_GET['poe_mode'])) {
    $poe_mode = $_GET['poe_mode'];
} elseif (isset($argv[1])) {
    $poe_mode = $argv[1];
} else {
    $poe_mode = 'auto';
}

$new_ports_config = [
    'port_overrides' => update_ports($current_conf, $lanports, $poe_mode)
];

$update_device = $unifi_connection->set_device_settings_base($device_id, $new_ports_config);

if (!$update_device) {
    $error = $unifi_connection->get_last_results_raw();
    echo json_encode($error, JSON_PRETTY_PRINT);
}

echo json_encode($update_device, JSON_PRETTY_PRINT);