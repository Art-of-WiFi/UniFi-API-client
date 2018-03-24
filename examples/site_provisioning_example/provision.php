<?php
/**
 * PHP API usage example
 *
 * contributed by: @smos
 * description: example provisioning script to create a large number of sites with comparable network configurations
 */

/* include important files */
require_once('UniFi-API-client/src/Client.php');

/* Set the default timezone */
date_default_timezone_set('Europe/Amsterdam');

// Example array with site information, includes numeric reference
$fil_array = array();
$fil_array[600]['aktief'] = 1; // Active
$fil_array[600]['kassa_aantal'] = 1; // Cash registers
$fil_array[600]['divisie_code'] = "D"; // Brand
$fil_array[600]['corr_woonplaats'] = "Amsterdam"; // City


echo "<pre>";
// Import the controller auth config
include("config.php");

/**
 * set to true (without quotes) to enable debug output to the browser and the PHP error log
 */
$debug = true;

$site_id = "default";

/**
 * initialize the UniFi API connection class, log in to the controller and request the alarms collection
 * (this example assumes you have already assigned the correct values to the variables used)
 */
$unifi_connection = new UniFi_API\Client($controller_user, $controller_password, $controller_url, $site_id, $controller_version, false);
$login            = $unifi_connection->login();

if($login > 400) {
    echo "Failed to log into controller";
    die;
}
// $sites = $unifi_connection->list_sites(); // returns a PHP array containing sites

$open_shops = array();
// Mogrify shop info into useable arrays
foreach($fil_array as $filnr => $shop) {
    if(floatval($shop['aktief']) == 0)
        continue;
    if($shop['divisie_code'] != "D")
        continue;

    if((floatval($shop['kassa_aantal']) > 0))
        $open_shops[$filnr] = ucfirst(strtolower($shop['corr_woonplaats']));
}

// If debug, create Fake open shops array, otherwise unset test shops
if($debug === true) {
    $open_shops = array();
    $open_shops[943] = "Test 1";
    $open_shops[965] = "Test 2";
} else {
    unset($open_shops[943]);
    unset($open_shops[965]);
    unset($close_shops[943]);
    unset($close_shops[965]);
}

// Check if we can find all our shop sites, otherwise add to todo list for creation, close list for deletion
$todo_shops = $open_shops;
$active_shops = array();
$close_shops = array();
foreach($unifi_connection->list_sites() as $site){
    $desc = $site->desc;
    // Does it look like a shop?
    if(preg_match("/([0-9][0-9][0-9]+)/", $desc, $matches)) {
        // echo "Found site {$desc}\n";
        unset($todo_shops[floatval($matches[1])]);
        $active_shops[floatval($matches[1])] = $site->name;

        if(!$open_shops[floatval($matches[1])]) {
            // echo "Shop {$matches[1]} does not have hardware\n";
            $close_shops[floatval($matches[1])] = $site->name;
        }
    }
}

// Any sites we need to create before we can continue?
foreach($todo_shops as $filnr => $city){
    $filnr = sprintf("%04d", $filnr);
    $desc = "{$filnr} {$city}";
    echo "Create site for {$filnr}\n";
    $createsite = $unifi_connection->create_site($desc);
    if($createsite === false) {
        echo "Failed to create site for {$filnr}, id {$siteid}\n";
        break;
    }
}
// Refresh site list
if(count($todo_shops > 0)) {
    foreach($unifi_connection->list_sites() as $site){
        $desc = $site->desc;
        // Does it look like a shop?
        if(preg_match("/([0-9][0-9][0-9]+)/", $desc, $matches)) {
            // echo "Found site {$desc}\n";
            unset($todo_shops[floatval($matches[1])]);
            $active_shops[floatval($matches[1])] = $site->name;
        }
    }
}

// If debug, create Fake site entries array, otherwise unset test shops
if($debug === true) {
    $close_shops = array();
    $active_shops = array();
    $active_shops[965] = "j103b83q";
    $active_shops[943] = "winkels";
} else {
    unset($active_shops[943]);
    unset($active_shops[965]);
    unset($close_shops[943]);
    unset($close_shops[965]);
}
// We should have 0 todo shops now
// print_r($todo_shops);

/*
echo "Open\n";
print_r($open_shops);
echo "Active\n";
print_r($active_shops);
echo "Close\n";
print_r($close_shops);
die();
*/

// Foreach shop, select the site.
foreach($active_shops as $filnr => $siteid) {
    $filnr = sprintf("%04d", $filnr);
    $select = $unifi_connection->set_site($siteid);

    // fetch configured group settings, we need those later, we only use the Default group.
    $wlangroups = $unifi_connection->list_wlan_groups($siteid);
    $usergroups = $unifi_connection->list_usergroups($siteid);

    if(isset($close_shops[floatval($filnr)])) {
        echo "Delete site {$siteid} with id ". $usergroups[0]->site_id ." for shop {$filnr}\n";
        if($debug===false) {
            $delete = $unifi_connection->delete_site($usergroups[0]->site_id);
        }
        if($delete === false) {
            echo "Failed to delete site for {$filnr}, id {$siteid}\n";
        }
        continue;
    }

    // fetch configured group settings, we need those later, we only use the Default group.
    $wlangroups = $unifi_connection->list_wlan_groups($siteid);
    $usergroups = $unifi_connection->list_usergroups($siteid);
    if($debug===true) {
        //var_export ($wlangroups);
        //var_export ($usergroups);
    }
    foreach($wlangroups as $group){
        // Check if template networks exist
        if($group->name == "Default") {
            $shawlangroup_id = $group->_id;
        }
    }
    foreach($usergroups as $group){
        // Check if template networks exist
        if($group->name == "Default") {
            $shausergroup_id = $group->_id;
        }
    }

    // Include each time so site specific settings based on shop number are picked up
    unset($wirednetworks);
    unset($wlannetworks);
    unset($siteconf);
    include("settings.php");

    refresh_networks();
    refresh_wlans();
    fetch_site_conf();

    if($debug===true) {
        // var_export ($siteconf);
        //var_export ($wlanconf);
        // var_export ($networkconf);
        //print_r($wlannetworks);
    }


    foreach($sitesettings as $key => $values) {
        if(compare_array_item($sitesettings[$key], $setting[$key])) {
            echo "Update site setting {$key} id {$setting_id[$key]} for {$filnr}, id {$siteid}\n";
            switch($key){
                case "country":
                    $update_site[$key] = $unifi_connection->set_site_country($setting_id[$key], $sitesettings[$key]);
                    break;
                case "locale":
                    $update_site[$key] = $unifi_connection->set_site_locale($setting_id[$key], $sitesettings[$key]);
                    break;
                case "connectivity ":
                    $update_site[$key] = $unifi_connection->set_site_connectivity($setting_id[$key], $sitesettings[$key]);
                    break;
                case "mgmt":
                    $update_site[$key] = $unifi_connection->set_site_mgmt($setting_id[$key], $sitesettings[$key]);
                    break;
                case "guest_access":
                    $update_site[$key] = $unifi_connection->set_site_guest_access($setting_id[$key], $sitesettings[$key]);
                    break;
                case "snmp":
                    $update_site[$key] = $unifi_connection->set_site_snmp($setting_id[$key], $sitesettings[$key]);
                    break;
                case "ntp":
                    $update_site[$key] = $unifi_connection->set_site_ntp($setting_id[$key], $sitesettings[$key]);
                    break;
                default:
                    break;
            }
        }
        if($update_site[$key] === false)
            echo "Failed to update setting {$key} for {$filnr}, id {$siteid} ". print_r($sitesettings[$key], true) ."\n";
    }

    foreach($wirednetworks as $key => $values) {
        // Template network didn't exist, create
        if($wired[$key] === false) {
            echo "Create new vlan {$key} for {$filnr}, id {$siteid}\n";
            $addnetwork[$key] = $unifi_connection->create_network($wirednetworks[$key]);
            // echo json_encode($addvlan, JSON_PRETTY_PRINT);
        }
        if($addnetwork[$key] === false)
            echo "Failed to add network {$key} for {$filnr}, id {$siteid}\n";

        // Do we need to update?
        if(compare_array_item($wirednetworks[$key], $wired[$key])) {
            echo "Update network {$key} id {$wired_id[$key]} for {$filnr}, id {$siteid}\n";
            $updatenetwork[$key] = $unifi_connection->set_networksettings_base($wired_id[$key], $wirednetworks[$key]);
        }
        if($updatenetwork[$key] === false)
            echo "Failed to update network {$key} for {$filnr}, id {$siteid} ". print_r($wirednetworks[$key], true) .  print_r($wired_id, true) ."\n";

    }

    foreach($wlannetworks as $key => $values) {
        // Template network didn't exist, create
        if($wlan[$key] === false) {
            echo "Create new disabled wlan {$key} for {$filnr}, id {$siteid}\n";
            $addwlan[$key] = $unifi_connection->create_wlan($wlannetworks[$key]['name'], $wlannetworks[$key]['x_passphrase'], $wlannetworks[$key]['usergroup_id'], $wlannetworks[$key]['wlangroup_id'], false);
        }
        if($addwlan[$key] === false)
            echo "Failed to add wlan {$key} for {$filnr}, id {$siteid} ". print_r($wlannetworks[$key], true) ."\n";
        else
            refresh_wlans();

        // Do we need to update?
        if(compare_array_item($wlannetworks[$key], $wlan[$key])) {
            echo "Update wlan {$key} id {$wlan_id[$key]} for {$filnr}, id {$siteid}\n";
            $updatewlan[$key] = $unifi_connection->set_wlansettings_base($wlan_id[$key], $wlannetworks[$key]);
        }
        if($updatewlan[$key] === false)
            echo "Failed to update wlan {$key} for {$filnr}, id {$siteid} ". print_r($wlannetworks[$key], true) . print_r($wlan_id, true) ."\n";

    }

    // Any devices for adoption?
    $devices[$filnr] = $unifi_connection->list_devices();
    foreach($devices[$filnr] as $device) {
        if($device->adopted == 1)
            continue;

        // Does this unadopted device belong to this shop network?
        if(netMatch($wirednetworks['LAN']['ip_subnet'], $device->ip)) {
            // Adopt device in IP range. adopt_device($mac)
            echo "Adopting device {$device->mac} with ip {$device->ip} in network {$wirednetworks['LAN']['ip_subnet']} for shop {$filnr}\n";
            $unifi_connection->adopt_device($device->mac);
            // print_r($device);
        }
    }

    if($debug===true) {
        //break;
    }
}

$logout = $unifi_connection->logout();

function refresh_networks() {
    global $unifi_connection;
    global $networkconf;
    global $wired;
    global $wired_id;
    global $shasite_id;
    global $wirednetworks;

    // Fetch configured wired networks
    $networkconf = $unifi_connection->list_networkconf();

    foreach($wirednetworks as $key => $values) {
        $wired[$key] = false;
    }
    // Lan netwerken
    foreach($networkconf as $network){
        // Check if template networks exist
        foreach($wirednetworks as $key => $values) {
            if(($network->name == "$key")) {
                $wired[$key] = $network;
                $wired_id[$key] = $network->_id;
                $shasite_id = $network->site_id;
            }
        }
    }
}

function refresh_wlans() {
    global $unifi_connection;
    global $wlanconf;
    global $wlan;
    global $wlan_id;
    global $shasite_id;
    global $wlannetworks;

    // Fetch Wireless networks
    $wlanconf = $unifi_connection->list_wlanconf();

    foreach($wlannetworks as $key => $values)
        $wlan[$key] = false;

    foreach($wlanconf as $network){
        // Check if template networks exist
        foreach($wlannetworks as $key => $values) {
            if($network->name == "$key") {
                $wlan[$key] = $network;
                $wlan_id[$key] = $network->_id;
                $shasite_id = $network->site_id;
            }
        }
    }
}

function fetch_site_conf() {
    global $unifi_connection;
    global $siteconf;
    global $siteid;
    global $sitesettings;
    global $setting;
    global $setting_id;

    // Fetch site settings
    $siteconf = $unifi_connection->list_settings($siteid);
    foreach($sitesettings as $key => $values)
        $sitesetting[$key] = false;

    $setting = array();
    foreach($siteconf as $arr) {
        $setting[$arr->key] = $arr;
        $setting_id[$arr->key] = $arr->_id;
    }
}

// Return true or false
function compare_array_item($setting, $existing) {
    $existing = (array)$existing;
    unset($setting['site_id']);
    unset($setting['_id']);
    unset($existing['_id']);
    unset($existing['site_id']);
    foreach($setting as $key => $value) {
        if(!is_array($setting[$key])) {
            if($setting[$key] != $existing[$key]){
                echo "setting key {$key} value {$value} differs from {$existing[$key]} - ";
                // print_r($setting);
                // print_r($existing);
                return true;
            }
        }
        if(is_array($setting[$key])) {
            $diff = array();
            $diff = array_diff_assoc($setting[$key], $existing[$key]);
            if(!empty($diff)) {
                echo "setting subkey {$key} differs diff count ". count($diff)."\n";
                // print_r($diff);
                // print_r($setting);
                // print_r($existing);
                return true;
            }
        }
    }
    return false;
}
?>