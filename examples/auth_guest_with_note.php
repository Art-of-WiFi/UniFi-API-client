<?php
/**
 * PHP API usage example
 *
 * contributed by: Art of WiFi
 * description: example basic PHP script to auth a guest device and attach a note to it,
 *              this requires the device to be connected to the WLAN/LAN at moment of
 *              authorization
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
 * the MAC address of the device to authorize
 */
$mac = '<enter MAC address of guest device to auth>';

/**
 * the duration to authorize the device for in minutes
 */
$duration = 2000;

/**
 * The site to authorize the device with
 */
$site_id = '<enter your site id here>';

/**
 * the note to attach to the device
 */
$note = 'Note to attach to newly authorized device';

/**
 * initialize the UniFi API connection class and log in to the controller
 */
$unifi_connection = new UniFi_API\Client($controlleruser, $controllerpassword, $controllerurl, $site_id, $controllerversion);
$set_debug_mode   = $unifi_connection->set_debug($debug);
$loginresults     = $unifi_connection->login();

/**
 * we authorize the device for the requested duration and attach the note to it's object
 */
$auth_result  = $unifi_connection->authorize_guest($mac, $duration);
$getid_result = $unifi_connection->stat_client($mac);
$user_id      = $getid_result[0]->_id;
$note_result  = $unifi_connection->set_sta_note($user_id, $note);

/**
 * When using older Controller versions (< 5.5.x) to attach a note to a new (unconnected) device, we instead need to take the
 * following steps before authorizing the device:
 * - first block the device to get an entry in the user collection
 * - get the device id from the user collection
 * - attach note to the device
 * - then unblock the device again **after the authorization has taken place**
 */
//$block_result   = $unifi_connection->block_sta($mac);
//$getid_result   = $unifi_connection->stat_client($mac);
//$user_id        = $getid_result[0]->_id;
//$note_result    = $unifi_connection->set_sta_note($user_id, $note);
//$unblock_result = $unifi_connection->unblock_sta($mac);
//$auth_result    = $unifi_connection->authorize_guest($mac, $duration);

/**
 * provide feedback in json format
 */
echo json_encode($auth_result, JSON_PRETTY_PRINT);