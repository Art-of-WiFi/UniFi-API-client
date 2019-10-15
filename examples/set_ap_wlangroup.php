<?php
/**
 * PHP API usage example
 *
 * contributed by: gahujipo
 * description: example to change the wlan group of an AP
 */

/**
 * using the composer autoloader
 */
require_once('vendor/autoload.php');

/**
 * include the config file (place your credentials etc there if not already present)
 * see the config.template.php file for an example
 */
require_once('config.php');

/**
 * the short name of the site which you wish to query
 */
$site_id = '<enter your site id here>';

/**
 * the short name of the AP which you wish to change
 */
$device_name      = '<device_name>';

/**
 * the short name of the wlangroup you wish to assign
 */
$wlangroup_name   = '<wlangroup_name>';


/**
 * initialize the UniFi API connection class and log in to the controller and pull the requested data
 */
$unifi_connection = new UniFi_API\Client($controlleruser, $controllerpassword, $controllerurl, $site_id, $controllerversion);
$set_debug_mode   = $unifi_connection->set_debug($debug);
$loginresults     = $unifi_connection->login();


$wlan_groups = $unifi_connection->list_wlan_groups();
$wlangroup_id = null;
foreach ($wlan_groups as $group) {
    if ($group->name == $wlangroup_name) {
        $wlangroup_id = $group->_id;
    }
}
if (!$wlangroup_id) {
    return false;
}


$devices = $unifi_connection->list_devices();
$device_id = null;
foreach ($devices as $device) {
    if ($device->name == $device_name) {
        $device_id = $device->_id;
    }
}
if (!$device_id) {
    return false;
}

$result1 = $unifi_connection->set_ap_wlangroup('ng', $device_id, $wlangroup_id);
$result2 = $unifi_connection->set_ap_wlangroup('na', $device_id, $wlangroup_id);

/**
 * output the results in JSON format
 */
header('Content-Type: application/json; charset=utf-8');

echo json_encode(
    array(
        $result1,
        $result2
    )
);
