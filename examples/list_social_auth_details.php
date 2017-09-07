<?php
/**
 * PHP API usage example
 *
 * contributed by: Art of WiFi
 * description: example basic PHP script to pull Facebook social auth details from the UniFi controller and output
 *              them in basic HTML format
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
 * the site to use
 */
$site_id = '<enter your site id here>';

/**
 * initialize the UniFi API connection class and log in to the controller and do our thing
 */
$unifi_connection = new UniFi_API\Client($controlleruser, $controllerpassword, $controllerurl, $site_id, $controllerversion);
$set_debug_mode   = $unifi_connection->set_debug($debug);
$loginresults     = $unifi_connection->login();
$data             = $unifi_connection->stat_payment();

/**
 * cycle through the results and print social auth details if set,
 * at this stage you can choose to do with the payment objects whatever is needed
 */
echo 'Results from Facebook social auth:<br>';
foreach ($data as $payment) {
    if (isset($payment->gateway) && $payment->gateway == 'facebook') {
        echo 'First name: ' . $payment->first_name . ' Last name: ' . $payment->last_name . ' E-mail address: ' . $payment->email . '<br>';
    }
}

echo '<hr><br>';