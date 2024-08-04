<?php
/**
 * PHP API usage example
 *
 * contributed by: Art of WiFi
 * description: example to toggle the site-wide auto upgrade setting on a UniFi controller
 */

/**
 * using the composer autoloader
 */
require_once('vendor/autoload.php');

/**
 * include the config file (place your credentials etc. there if not already present)
 * see the config.template.php file for an example
 *
 * @var string $controlleruser the user name for access to the UniFi Controller
 * @var string $controllerpassword the password for access to the UniFi Controller
 * @var string $controllerurl full url to the UniFi Controller, eg. 'https://
 * @var string $controllerversion the version of the Controller software, eg. '4.6.6' (must be at least 4.0.0)
 * @var bool $debug set to true (without quotes) to enable debug output to the browser and the PHP error log
 */
require_once('config.php');

/**
 * site id for the site where settings are to be updated
 */
$site_id = 'zzzzz';

/**
 * initialize the UniFi API connection class and log in to the controller to do our thing
 */
$unifi_connection = new UniFi_API\Client(
    $controlleruser,
    $controllerpassword,
    $controllerurl,
    $site_id,
    $controllerversion
);

$login_results = $unifi_connection->login();

if ($login_results) {
    /**
     * we get the current site mgmt settings
     */
    $current_site_settings = $unifi_connection->list_settings();

    $mgmt_settings = [];
    foreach ($current_site_settings as $section) {
        if ($section->key == 'mgmt') {
            $mgmt_settings = $section;
        }
    }

    /**
     * toggle the auto upgrade setting and set the auto upgrade hour to 3
     */
    $mgmt_settings->auto_upgrade      = !$mgmt_settings->auto_upgrade;
    $mgmt_settings->auto_upgrade_hour = 3;
    $mgmt_id                          = $mgmt_settings->_id;
    $set_result                       = $unifi_connection->set_site_mgmt($mgmt_id, $mgmt_settings);

    echo 'done' . PHP_EOL;
    exit();
}

echo 'login failed' . PHP_EOL;