<?php
/**
 * PHP API usage example
 *
 * contributed by: mtotone
 * description: example of how to extend validity of guest authorizations
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
 * must be adapted to your site!
 */
$site_id   = "default";
$site_name = "*enter your site name*";

$unifi_connection = new UniFi_API\Client($controlleruser, $controllerpassword, $controllerurl, $site_id, $controllerversion);
$set_debug_mode   = $unifi_connection->set_debug($debug);
$loginresults     = $unifi_connection->login();

if ($loginresults === 400) {
    print "UniFi controller login failure, please check your credentials in config.php.\n";
} else {
    $guestlist = $unifi_connection->list_guests();

    /**
     * loop thru all known guests
     */
    foreach ($guestlist as $guest) {
        print "<pre>" . $guest->_id . " (" . $guest->mac . "), valid until " . date(DATE_ATOM, $guest->end) . " (" . $guest->end . ")</pre>";

        /**
         * just a sample: only extend validity of guests which have end date after 2017-04-02
         */
        if ($guest->end > 1491166482) {
            /**
             * extend clients five times = five days
             */
            if (!$unifi_connection->extend_guest_validity($guest->_id)) {
                print "Extend failed for guest with id " . $guest->_id . "\n";
            }

            if (!$unifi_connection->extend_guest_validity($guest->_id)) {
                print "Extend failed for guest with id " . $guest->_id . "\n";
            }

            if (!$unifi_connection->extend_guest_validity($guest->_id)) {
                print "Extend failed for guest with id " . $guest->_id . "\n";
            }

            if (!$unifi_connection->extend_guest_validity($guest->_id)) {
                print "Extend failed for guest with id " . $guest->_id . "\n";
            }

            if (!$unifi_connection->extend_guest_validity($guest->_id)) {
                print "Extend failed for guest with id " . $guest->_id . "\n";
            }
        }
    }

    $logout_results = $unifi_connection->logout();
}