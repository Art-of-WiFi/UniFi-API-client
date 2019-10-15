<?php
/**
 * PHP API usage example
 *
 * contributed by: Art of WiFi
 * description: example basic PHP script to modify the super_mgmt settings for UniFi controller and output results
 *              in json format
 */

/**
 * using the composer autoloader
 */
require_once('vendor/autoload.php');

/**
 * UniFi controller credentials and the site to use, in this case since we are modifying global settings you can select
 * any site here that is available on the UniFi controller
 *
 * NOTE: in this case you need to enter Super Administrator account credentials in config.php
 */
require_once('config.php');
$site_id = 'default';
$debug   = false;

/**
 * initialize the UniFi API connection class and log in to the controller and do our thing
 */
$unifi_connection = new UniFi_API\Client($controlleruser, $controllerpassword, $controllerurl, $site_id, $controllerversion, true);
$set_debug_mode   = $unifi_connection->set_debug($debug);
$loginresults     = $unifi_connection->login();
$site_settings    = $unifi_connection->list_settings();

$super_mgmt_settings = [];
$super_mgmt_settings_id = '';

if (!empty($site_settings)) {
    foreach($site_settings as $section) {
        echo 'section key: ' . $section->key . PHP_EOL;
        if ($section->key === 'super_mgmt') {
            $super_mgmt_settings = $section;
            $super_mgmt_settings_id = $section->_id;
        }
    }
}

/**
 * modify the super_mgmt settings, in this example we only modify the Live Chat settings
 * uncomment the required new value below:
 */
//$super_mgmt_settings->live_chat = 'disabled';
//$super_mgmt_settings->live_chat = 'enabled';
$super_mgmt_settings->live_chat = 'super-only';

/**
 * we echo the parameters which we will be passing to the UniFi controller API
 */
echo $super_mgmt_settings_id . PHP_EOL;
echo json_encode($super_mgmt_settings, JSON_PRETTY_PRINT) . PHP_EOL . PHP_EOL;

$update_results = $unifi_connection->set_super_mgmt_settings_base($super_mgmt_settings_id, $super_mgmt_settings);

/**
 * provide feedback in json format
 */
echo json_encode($update_results, JSON_PRETTY_PRINT);
