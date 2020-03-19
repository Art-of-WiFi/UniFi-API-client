<?php
/**
 * PHP API usage example
 *
 * contributed by: @malcolmcif, based on another Art of WiFi example
 * description: basic PHP script to block a list of mac addresses passed in via command line,
 *              output is to console in non json format
 *
 * usage:
 *  php block_list.php <list of comma seperated mac addresses>
 *
 * example:
 *  php block_list.php 09:09:09:09:09:09,10:10:10:10:10:10
 *
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
$debug = false;

/**
 * the MAC address(es) of the device(s) to block
 */
$macs_to_block = explode(',', $argv[1]);

/**
 * The site to authorize the device with
 */
$site_id = 'MUST_DEFINE_THIS';
if ($site_id == "MUST_DEFINE_THIS") {
    print 'ERROR: set the site id in your script';
    return;
}

/**
 * initialize the UniFi API connection class and log in to the controller
 */
$unifi_connection = new UniFi_API\Client($controlleruser, $controllerpassword, $controllerurl, $site_id, $controllerversion);
$set_debug_mode   = $unifi_connection->set_debug($debug);
$loginresults     = $unifi_connection->login(); // always true regardless of site id

foreach ($macs_to_block as $mac) {
    // block_result is always true even if mac address does not exist :(
    $block_result = $unifi_connection->block_sta($mac);

    /**
     * NOTE:
     * during testing I had some strange behavior where clients were not reconnecting to the network correctly,
     * they appeared unblocked and received a valid IP address but could not actually get any data.
     * the clients did not come to life until I disabled the SSID and then re enabled it.
     * I guessed maybe these commands were occurring too quickly for the controller so I have slowed them down;
     * since introducing the sleep I have not seen the above behavior so it might be fixed
     */
    sleep(1);

    $getid_result = $unifi_connection->stat_client($mac);

    if (property_exists($getid_result[0], "oui")) {
        // this field(manufacturer) seems to exist on valid mac addresses
        if (property_exists($getid_result[0], "name")) {
            // this is the alias field if it has been defined
            $name = $getid_result[0]->name;
        } else {
            $name = $getid_result[0]->hostname;
        }
        print 'blocked ' . $name . PHP_EOL;
    } else {
        print 'ERROR: could not block ' . $mac . PHP_EOL;
        print '       check mac address is valid and part of your network' . PHP_EOL;
    }
}

/**
 * No json formatted data
 */
//echo json_encode($block_result, JSON_PRETTY_PRINT);