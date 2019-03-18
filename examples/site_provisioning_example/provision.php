<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL && ~E_NOTICE);

/**
 * PHP API usage example
 *
 * contributed by: @smos
 * description: example provisioning script to create a large number of sites with comparable network configurations
 */
$debug = true;
/* Do we want to do firmware upgrades ? */
$firmwareupgrade = true;

/* include important files */
// include("include/prog_functions.php");
require_once('UniFi-API-client/src/Client.php');

/* Set the default timezone */
date_default_timezone_set('Europe/Amsterdam');


// Import the controller auth config
include("config.php");
// Example array with site information, includes numeric reference
$fil_array = array();
$fil_array[600]['aktief'] = 1; // Active
$fil_array[600]['kassa_aantal'] = 1; // Cash registers
$fil_array[600]['divisie_code'] = "D"; // Brand
$fil_array[600]['corr_woonplaats'] = "Amsterdam"; // City



if($debug === true)
	echo "<pre>";

$site_id = "default";
$site_arr = array();

/**
 * initialize the Unifi API connection class, log in to the controller and request the alarms collection
 * (this example assumes you have already assigned the correct values to the variables used)
 */
$unifi_connection = new UniFi_API\Client($controller_user, $controller_password, $controller_url, $site_id, $controller_version, false);
$login            = $unifi_connection->login();

if($login === false) { 
	echo "Failed to log into controller";
	echo print_r($login, true);
	die;
}

if($login > 400) {
	echo "Failed to log into controller";
	echo print_r($login, true);
	die;
}

if($debug === true)
	echo "We managed to log on to $controller_url with $controller_user\n";

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
	$open_shops[942] = "Test 1";
	$open_shops[943] = "Test 2";
	$open_shops[965] = "Test 3";
} else {
	unset($open_shops[942]);
	unset($open_shops[943]);
	unset($open_shops[965]);
	unset($close_shops[942]);
	unset($close_shops[943]);
	unset($close_shops[965]);
}

// Check if we can find all our shop sites, otherwise add to todo list for creation, close list for deletion
$todo_shops = $open_shops;
$active_shops = array();
$close_shops = array();

if((count($open_shops) < 10) && ($debug === false)){
	echo "Less then 10 open shops, aborting\n";
	die();
}

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
		echo "Failed to create site for {$filnr}, site id {$siteid}\n";
		break;
	}
}
// Refresh site list
if(count($todo_shops > 0)) {
	foreach($unifi_connection->list_sites() as $site){
		$desc = $site->desc;
		$site_arr[$site->_id] = $desc;
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
	$active_shops[942] = "fyvgzxed";
	$active_shops[943] = "winkels";
	$active_shops[965] = "1u0aift3";
} else {
	unset($active_shops[942]);
	unset($active_shops[943]);
	unset($active_shops[965]);
	unset($close_shops[942]);
	unset($close_shops[943]);
	unset($close_shops[965]);
}

// We should have 0 todo shops now
// print_r($todo_shops);
/*
if($debug === true) {
	echo "Open\n";
	print_r($open_shops);
	echo "Active\n";
	print_r($active_shops);
	echo "Close\n";
	print_r($close_shops);
	echo "Todo\n";
	print_r($close_shops);
}
*/

if($debug === true)
	echo "We found ". count($open_shops) ." open shops, ". count($close_shops) ." to delete\n";

// Foreach shop, select the site.
foreach($active_shops as $filnr => $siteid) {
	$filnr = sprintf("%04d", $filnr);
	$select = $unifi_connection->set_site($siteid);

	if($debug === true)
		echo "Selecting site {$siteid} for shop {$filnr}\n";

	// fetch configured group settings, we need those later, we only use the Default group.
	$wlangroups = $unifi_connection->list_wlan_groups($siteid);
	$usergroups = $unifi_connection->list_usergroups($siteid);

	if(isset($close_shops[floatval($filnr)])) {
		// Unless debug is false, don't delete anything
		if($debug===false) {
			echo "Delete site {$siteid} with id ". $usergroups[0]->site_id ." for shop {$filnr}\n";
			$delete = $unifi_connection->delete_site($usergroups[0]->site_id);
		}
		if($delete === false) {
			echo "Failed to delete site for {$filnr}, site id {$siteid}\n";
		}
		continue;
	}

	// fetch configured group settings, we need those later, we only use the Default group.
	$wlangroups = $unifi_connection->list_wlan_groups($siteid);
	$usergroups = $unifi_connection->list_usergroups($siteid);
	$shasite_id = $wlangroups->site_id;
	
	if($debug===true) {
		// var_export ($wlangroups);
		// var_export ($usergroups);
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
		
	$client_list = $unifi_connection->list_clients();
	// Build a mac address to switch port table
	foreach($client_list as $client){
		// var_export($client);
		foreach($vendors as $category => $address_arr) {
			foreach($address_arr as $hwmac) {
				// var_export($hwmac);
				//echo "checking for {$hwmac} in {$client->mac}\n";
				if(preg_match("/^{$hwmac}/i", $client->mac))
					$arp_table[$filnr][$category][$client->mac] = $client->sw_port;		
			}
		}
		
	}

	// Unset previous siteconf
	unset($siteconf);
	include("./settings.php");
	fetch_site_conf();
	
	foreach($site_settings as $key => $values) {
		if(compare_array_item($site_settings[$key], $setting[$key])) {
			echo "Update site setting {$key} id {$setting_id[$key]} for {$filnr}, site id {$siteid}\n";
			switch($key){
				case "country":
					$update_site[$key] = $unifi_connection->set_site_country($setting_id[$key], $site_settings[$key]);
					break;
				case "locale":
					$update_site[$key] = $unifi_connection->set_site_locale($setting_id[$key], $site_settings[$key]);
					break;
				case "connectivity ":
					$update_site[$key] = $unifi_connection->set_site_connectivity($setting_id[$key], $site_settings[$key]);
					break;
				case "mgmt":
					$update_site[$key] = $unifi_connection->set_site_mgmt($setting_id[$key], $site_settings[$key]);
					break;
				case "guest_access":
					$update_site[$key] = $unifi_connection->set_site_guest_access($setting_id[$key], $site_settings[$key]);
					break;
				case "snmp":
					$update_site[$key] = $unifi_connection->set_site_snmp($setting_id[$key], $site_settings[$key]);
					break;
				case "ntp":
					$update_site[$key] = $unifi_connection->set_site_ntp($setting_id[$key], $site_settings[$key]);
					break;
				case "porta":
					$update_site[$key] = $unifi_connection->set_site_porta($setting_id[$key], $site_settings[$key]);
					break;
				case "usg":
					$update_site[$key] = $unifi_connection->set_site_usg($setting_id[$key], $site_settings[$key]);
					break;
				default:
					break;	
			}
		}
		if($update_site[$key] === false)
			echo "Failed to update setting {$key} for {$filnr}, site id {$siteid} ". print_r($site_settings[$key], true) ."\n";
	}
	if($debug === true)
		echo "Finished updating site settings\n";

	// Compare running config to settings, flag settings that need changes. FW Groups before rules.
	$func_arr = array("wlans" => "list_wlanconf", "networks" => "list_networkconf", "dynamicdns" => "list_dynamicdns", "fwgroups" => "list_firewallgroup", "fwrules" => "list_firewallrule");
	$func_add_arr = array("wlans" => "create_wlan", "networks" => "create_network", "dynamicdns" => "add_dynamicdns", "fwgroups" => "add_firewallgroup", "fwrules" => "add_firewallrule");
	$func_set_arr = array("wlans" => "set_wlansettings_base", "networks" => "set_networksettings_base", "dynamicdns" => "set_dynamicdns", "fwgroups" => "set_firewallgroup", "fwrules" => "set_firewallrule");

	// Unset previous siteconf
	unset($siteconf);
	include("./settings.php");
	fetch_site_conf();
	foreach ($func_arr as $name => $function) {
		$name_conf = $name . "_conf";
		$name_settings = $name . "_settings";
		$name_id = $name ."_id";
		unset($$name);
		unset($$name_id);
		unset($$name_conf);
		unset($$name_settings);
	}
	
	foreach ($func_arr as $name => $function) {
		$name_conf = $name . "_conf";
		$name_settings = $name . "_settings";
		$name_id = $name ."_id";
		// Include each time so site specific settings based on shop number are picked up
		include("./settings.php");
		if($debug === true)
			echo "Start comparing {$name} settings using function {$function}\n";
		
		$name_conf = $name . "_conf";
		$name_settings = $name . "_settings";
		$name_id = $name ."_id";

		refresh_settings($unifi_connection, $name, $function);
		// var_export($$name_conf);

		// Include each time so site specific settings based on shop number are picked up
		include("./settings.php");
		foreach(${$name_settings} as $key => $values) {
			// find the LAN and WAN network ids and add them to the fwgroups_id array
			if($name == "networks") {
				foreach($networks_conf as $network) {
					if($network->name == "WAN")
						$fwgroups_id[$network->name] = $network->_id;
					if($network->name == "LAN")
						$fwgroups_id[$network->name] = $network->_id;
				}
			}

			if(!isset(${$name_id}[$key])) {
				echo "Create new {$name} {$key} for {$filnr}, site id {$siteid}\n";
				if($name == "wlans") {
					$add[$name][$key] = $unifi_connection->{$func_add_arr[$name]}(${$name_settings}[$key]['name'], ${$name_settings}[$key]['x_passphrase'], ${$name_settings}[$key]['usergroup_id'], ${$name_settings}[$key]['wlangroup_id'], false);
				} else {
					$add[$name][$key] = $unifi_connection->{$func_add_arr[$name]}(${$name_settings}[$key]);
				}
				if($add[$name][$key] === false) {
					echo "Failed to add {$name} {$key} for {$filnr}, site id {$siteid}\n";
					continue;
				}
				refresh_settings($unifi_connection, $name, $function);
			}
			if($debug===true) {
				//var_export($$name[$key]);
			}
			// Do we need to update?
			if(compare_array_item(${$name_settings}[$key], ${$name}[$key])) {
			echo "Update {$name} {$key} id {${$name_id}[$key]} using function {$func_set_arr[$name]} for {$filnr}, site id {$siteid}\n";
				$update[$name][$key] = $unifi_connection->{$func_set_arr[$name]}(${$name_id}[$key], ${$name_settings}[$key]);
			}
			if($update[$name][$key] === false)
				echo "Failed to update network {$key} for {$filnr}, site id {$siteid} ". print_r(${$name_settings}[$key], true) .  print_r(${$name_id}, true) ."\n";
		}
	}
		
	if($debug===true) {
		// var_export ($siteconf);
		// var_export ($site_settings);
		// var_export ($wlans_conf);
		//var_export ($networks_conf);
		// var_export ($client_list);
		// print_r($wlannetworks);
		// var_export ($fwgroupconfs);
		// var_export($fwruleconfs);
		// var_export($dynamicdns_conf);
	}
	
	$devices[$filnr] = $unifi_connection->list_devices();
	if($debug === true) {
		// var_export($devices[$filnr]);
	}

	// Any adopted devices that need a static IP or specific settings?
	foreach($devices[$filnr] as $device) {
		if(($device->adopted == 1) && ($device->type == "usw")) {
			if($debug === true) {
				// var_export($device);
			}
			$index = 0;
			if(preg_match("/^US24/", $device->model)) {
				$index = 0;
			}
			if(preg_match("/^US8/", $device->model)) {
				$index = 1;
			}
			$s_octets = explode(".", $device->ip);
			if(floatval($s_octets[3]) > 9) {
				echo "Switch IP {$device->ip} is not set correctly yet, configuring {$switchconfig[$index]['config_network']['ip']}\n";
				$set_switch[$filnr] = $unifi_connection->set_device_settings_base($device->_id, $switchconfig[$index]);
			}
			if(compare_array_item($switchconfig[$index]['config_network'], $device->config_network)) {
				echo "Update device setting {$device->name} id {$device->_id} for {$filnr}, site id {$siteid}\n";
				$set_switch[$filnr] = $unifi_connection->set_device_settings_base($device->_id, $switchconfig[$index]);
			}
			if($set_switch[$filnr] === false)
				echo "Failed to set switch for {$filnr}, site id {$siteid} ". print_r($switchconfig[$index], true) . print_r($device->_id, true) ."\n";
		}
		if(($device->adopted == 1) && ($device->type == "ugw")) {
			$index = 0;
			if(preg_match("/^UGW3/", $device->model)) {
				$index = 0;
			}
			//echo "Update device setting {$device->name} id {$device->_id} for {$filnr}, site id {$siteid}". print_r((array)$device->ethernet_overrides, true) . print_r($ugwconfig[$index]['ethernet_overrides'], true) ."\n";
			if(compare_array_item($ugwconfig[$index]['ethernet_overrides'], (array)$device->ethernet_overrides)) {
				echo "Update device setting {$device->name} id {$device->_id} for {$filnr}, site id {$siteid}\n";
				$set_ugw[$filnr] = $unifi_connection->set_device_settings_base($device->_id, $ugwconfig[$index]);
			}
			if($set_ugw[$filnr] === false)
				echo "Failed to set ugw for {$filnr}, site id {$siteid} ". print_r($ugwconfig[$index], true) . print_r($device->_id, true) ."\n";
		}
	}
	
	// Any devices for adoption?
	foreach($devices[$filnr] as $device) {
		if(($device->adopted == 1) && ($device->state <> 9))
			continue;
		// Wireless scanned access points
		if(($device->discovered_via == "scan") && ($device->model == "U7LT"))
			continue;
		
		if(($device->adopted <> 1) || ($device->state == 9)) {
			if($usergroups[0]->site_id == $device->site_id) {
				echo "Found unadopted {$device->model} with mac {$device->mac} on site {$siteid} with site id {$device->site_id} and desc {$site_arr[$device->site_id]}\n";
				if($debug === true)
					var_export($device);
			}
		}

		// Does this unadopted device belong to this shop network?
		if(netMatch($wirednetworks['LAN']['ip_subnet'], $device->ip)) {
			// Adopt device in IP range. adopt_device($mac)
			echo "Adopting device {$device->mac} with ip {$device->ip} in network {$wirednetworks['LAN']['ip_subnet']} for shop {$filnr}\n";
			$unifi_connection->adopt_device($device->mac);
			// print_r($device);
		}
		
		$dyndns = "{$filnr}-ddns-router.dnsalias.net";
		$dyndnsip = gethostbyname($dyndns);
		// Does this unadopted device belong to this shop network?
		if($device->ip == $dyndnsip) {
			// Adopt device in IP range. adopt_device($mac)
			echo "Adopting device {$device->mac} with ip {$device->ip}, matches dyndns {$dyndnsip} for shop {$filnr}\n";
			$unifi_connection->adopt_device($device->mac);
			// print_r($device);
		}

	}
	if($debug === true)
		echo "Finished adopting devices\n";

	// check upgrades
	if((floatval(date("H")) == 9) && (floatval(date("w")) == 1) && ($firmwareupgrade === true)) {
		foreach($devices[$filnr] as $device) {
			// Manual upgrade for switches
			if(($device->upgradable === true) && ($device->upgrade_state == 0) && ($device->type == "usw")) {
				echo "Upgrading firmware for device {$device->model} with mac {$device->mac} on version {$device->version} to {$device->upgrade_to_firmware} and ip {$device->ip} shop {$filnr}\n";
				$upgrade[$filnr] = $unifi_connection->upgrade_device($device->mac);
				// Disable firmware upgrades for the rest of the run.
				$firmwareupgrade = false;
			}
			if(($device->upgradable === true) && ($device->upgrade_state == 0) && ($device->type == "uap")) {
				echo "Start rolling upgrade for shop {$filnr}\n";
				$unifi_connection->start_rolling_upgrade();
			}
		}
	}

	if($debug===true) {
		//break;
	}
}

$logout = $unifi_connection->logout();

if($debug === true)
	echo "Loggout succesful\n";

if($debug === true) {
	//var_export($arp_table);
}

// Query routerconfig
$r_result = array();
$q_routers = "SELECT id, hostname FROM $rtable";
$r_routers = mysqli_query($dbh_mysql, $q_routers);
$r_routers_num = mysqli_num_rows($r_routers);
// $_text .= print_sql_error($dbh_mysql, $q_routers);

$i = 0;
if($r_routers_num > 0) {
		while($i < $r_routers_num) {
			$router = mysqli_fetch_assoc($r_routers);
			// Check if we have this shop number in the unifi arp table
			if(preg_match("/([0-9]+)-/", $router['hostname'], $matches)) {
				if(is_array($arp_table[$matches[1]])) {
					$json_unifi = json_encode($arp_table[$matches[1]]);
					// print_r($json_unifi);
					$q_update = "UPDATE $rtable SET unifi = '{$json_unifi}' where id = '{$router['id']}'";
					$r_update = mysqli_query($dbh_mysql, $q_update);
				}
			}
			$i++;
		}
}

// compare settings to running config
function refresh_settings($unifi_connection, $name, $function) {
	global $shasite_id;
	global $debug;

	$name_conf = $name . "_conf";
	$name_settings = $name . "_settings";
	$name_id = $name ."_id";
	global ${$name};
	global ${$name_id};
	global ${$name_conf};
	global ${$name_settings};
	${$name_conf} = $unifi_connection->$function();
	// Set all settings to false
	foreach(${$name_settings} as $key => $values)
		${$name}[$key] = false;

	foreach(${$name_conf} as $conf){
		// Check if template networks exist, fill in ID
		foreach(${$name_settings} as $key => $values) {
			if(isset($conf->name)) {
				if($conf->name == "$key") {
					${$name}[$key] = $conf;
					${$name_id}[$key] = $conf->_id;
					$shasite_id = $conf->site_id;
				}
			}
			if(isset($conf->host_name)) {
				if($conf->host_name == "$key") {
					${$name}[$key] = $conf;
					${$name_id}[$key] = $conf->_id;
					$shasite_id = $conf->site_id;
				}
			}
		}
	}
	if(($debug === true) && ($name == "dynamicdns")) {
		// var_export($$name);
		// var_export($$name_id);
		// var_export($$name_conf);
		// var_export($$name_settings);
	}
	// return($$name);
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
				print_r($existing);
				return true;
			}
		}
		if(is_array($setting[$key])) {
			$diff = array();
			$diff = array_diff_assoc($setting[$key], (array)$existing[$key]);
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
