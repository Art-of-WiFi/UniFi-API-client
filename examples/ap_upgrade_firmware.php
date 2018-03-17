<?php

/** 
* Checks and upgrades AP firmware (can be scheduled with systemd/cron)
**/

require_once('vendor/autoload.php');
require_once('config.php');

// Because of a bug in the API, the site name is probably stuck at 'default' rather than what you actually named it:
// https://github.com/Art-of-WiFi/UniFi-API-browser/issues/35
$site_id = 'default';

// AP MAC address formatted with colons
$device_mac = 'de:ad:be:ef:01:23';

// If you have Debian/Ubuntu, grab the controller package version
// Need to have exec function enabled for php-cli
$controller_version  = exec("apt-cache show unifi | grep Version | sed 's/Version: //g;  s/-.*//g;'");

// Or, you can just give this manually:
// $controller_version = '5.7.20';

/**
 * initialize the Unifi API connection class, log in to the controller and request the alarms collection
 * (this example assumes you have already assigned the correct values to the variables used)
 */

$unifi_connection = new UniFi_API\Client($controller_user, $controller_password, $controller_url, $site_id, $controller_version, false);
$login            = $unifi_connection->login();

// Run the actual upgrade
$results = $unifi_connection->upgrade_device($device_mac);

/**
 * provide feedback in json format from $response given by upgrade_device();
 */
echo json_encode($results, JSON_PRETTY_PRINT);

?>

