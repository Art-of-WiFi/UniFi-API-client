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
$unifi_connection = new UniFi_API\Client(
    $controlleruser,
    $controllerpassword,
    $controllerurl,
    $site_id,
    $controllerversion
);

$set_debug_mode = $unifi_connection->set_debug($debug);
$loginresults   = $unifi_connection->login();

/**
 * change VLAN on port
 */
$port = 1;
$vlan = 200;

/**
 * MAC Address of the UniFi device
 */
$mac = '<enter your device mac address here>';

set_port_vlan($mac, $port, $vlan, $unifi_connection);

/**
 * Set specific VLAN on device port
 *
 * @param string $device_mac MAC Address of UNIFI device
 * @param int $port_idx Port number of UNIFI device, note: started by 1
 * @param int $vlan_id VLAN ID to set
 * @param UniFi_API\Client $unifi_connection
 *
 * @return bool true if result is success, false upon failure
 */
function set_port_vlan($device_mac, $port_idx, $vlan_id, $unifi_connection)
{
    $device = $unifi_connection->list_devices($device_mac);

    // if no device found
    if (count($device) == 0) {
        trigger_error('set_port_vlan() device not found');
        return false;
    }

    $port_table = $device[0]->port_table;
    $def_port   = null;

    // check if port exists
    $exist = false;
    foreach ($port_table as $key => $port) {
        if ($port->port_idx == $port_idx) {
            $exist    = true;
            $def_port = $port;
            break;
        }
    }

    if (!$exist) {
        trigger_error('set_port_vlan() port_idx not found on device');
        return false;
    }

    // check if vlan exists
    $native_networkconf_id = null;

    foreach ($unifi_connection->list_networkconf() as $key => $network) {
        if ($network->purpose == 'vlan-only' && $network->vlan == $vlan_id) {
            $native_networkconf_id = $network->_id;
        }
    }

    if ($native_networkconf_id === null) {
        trigger_error('set_port_vlan() vlan not exist');
        return false;
    }

    $exist = false;
    foreach ($device[0]->port_overrides as $key => $port) {
        if ($port->port_idx == $port_idx) {
            $exist                                                  = true;
            $device[0]->port_overrides[$key]->native_networkconf_id = $native_networkconf_id;
            break;
        }
    }

    if (!$exist) {
        $device[0]->port_overrides[] = [
            'port_idx'                              => isset($port_idx) ? $port_idx : 1,
            'setting_preference'                    => isset($setting_preference) ? $setting_preference : 'auto',
            'name'                                  => isset($def_port->name) ? $def_port->name : 'Port 1',
            'op_mode'                               => isset($def_port->op_mode) ? $def_port->op_mode : 'switch',
            'port_security_enabled'                 => isset($def_port->port_security_enabled) ? $def_port->port_security_enabled : false,
            'port_security_mac_address'             => isset($def_port->port_security_mac_address) ? $def_port->port_security_mac_address : [],
            'native_networkconf_id'                 => isset($native_networkconf_id) ? $native_networkconf_id : '',
            'excluded_networkconf_ids'              => isset($def_port->excluded_networkconf_ids) ? $def_port->excluded_networkconf_ids : [],
            'show_traffic_restriction_as_allowlist' => isset($def_port->show_traffic_restriction_as_allowlist) ? $def_port->show_traffic_restriction_as_allowlist : false,
            'forward'                               => isset($def_port->forward) ? $def_port->forward : 'customize',
            'lldpmed_enabled'                       => isset($def_port->lldpmed_enabled) ? $def_port->lldpmed_enabled : true,
            'voice_networkconf_id'                  => isset($def_port->voice_networkconf_id) ? $def_port->voice_networkconf_id : '',
            'stormctrl_bcast_enabled'               => isset($def_port->stormctrl_bcast_enabled) ? $def_port->stormctrl_bcast_enabled : false,
            'stormctrl_bcast_rate'                  => isset($def_port->stormctrl_bcast_rate) ? $def_port->stormctrl_bcast_rate : 100,
            'stormctrl_mcast_enabled'               => isset($def_port->stormctrl_mcast_enabled) ? $def_port->stormctrl_mcast_enabled : false,
            'stormctrl_mcast_rate'                  => isset($def_port->stormctrl_mcast_rate) ? $def_port->stormctrl_mcast_rate : 100,
            'stormctrl_ucast_enabled'               => isset($def_port->stormctrl_ucast_enabled) ? $def_port->stormctrl_ucast_enabled : false,
            'stormctrl_ucast_rate'                  => isset($def_port->stormctrl_ucast_rate) ? $def_port->stormctrl_ucast_rate : 100,
            'egress_rate_limit_kbps_enabled'        => isset($def_port->egress_rate_limit_kbps_enabled) ? $def_port->egress_rate_limit_kbps_enabled : false,
            'autoneg'                               => isset($def_port->autoneg) ? $def_port->autoneg : true,
            'isolation'                             => isset($def_port->isolation) ? $def_port->isolation : false,
            'stp_port_mode'                         => isset($def_port->stp_port_mode) ? $def_port->stp_port_mode : true,
            'port_keepalive_enabled'                => isset($def_port->port_keepalive_enabled) ? $def_port->port_keepalive_enabled : false
        ];

    }

    $payload = [
        'port_overrides' => $device[0]->port_overrides
    ];

    $result = $unifi_connection->set_device_settings_base($device[0]->device_id, $payload);

    if ($result) {
        return true;
    }

    return false;
}