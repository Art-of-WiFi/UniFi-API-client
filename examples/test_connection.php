<?php
/**
 * Test the connection to your UniFi controller
 *
 * contributed by: Art of WiFi
 * description:    PHP script to check/debug the connection to your controller using PHP and cURL
 */

/**
 * Include the config file (place your credentials etc. there if not already present),
 * see the config.template.php file for an example.
 * (will only be used here to get the URL to the controller)
 */
require_once 'config.php';

/**
 * Check whether the cURL module supports SSL
 * http://www.php.net/manual/en/function.curl-version.php
 */
if (!(curl_version()['features'] & CURL_VERSION_SSL)) {
    print PHP_EOL . 'SSL is not supported with this cURL installation!' . PHP_EOL;
}

/**
 * create cURL resource
 */
$ch = curl_init();

if (is_resource($ch) || is_object($ch)) {
    /**
     * If we have a resource or object (for PHP > 8.0), we proceed and set the required cURL options
     *
     * NOTES:
     * The cURL option CURLOPT_SSLVERSION can have a value of 0-6
     * see this URL for more details:
     * http://php.net/manual/en/function.curl-setopt.php
     * 0 is the default value and is used by the PHP API client class
     */
    $curl_options = [
        CURLOPT_PROTOCOLS      => CURLPROTO_HTTPS,
        CURLOPT_URL            => $controllerurl,
        CURLOPT_CUSTOMREQUEST  => 'GET',
        CURLOPT_SSL_VERIFYPEER => false,
        CURLOPT_SSL_VERIFYHOST => false,
        CURLOPT_VERBOSE        => true,
        CURLOPT_SSLVERSION     => 0,
        CURLOPT_HTTP_VERSION   => CURL_HTTP_VERSION_1_1,
    ];

    curl_setopt_array($ch, $curl_options);

    /**
     * $results contains the output as returned by the cURL request,
     * returns true when successful, else returns false
     */
    print PHP_EOL . 'verbose output from the cURL request:' . PHP_EOL;
    $results = curl_exec($ch);

    print PHP_EOL . 'curl_getinfo output:' . PHP_EOL;
    print_r(curl_getinfo($ch));

    /**
     * If we receive a cURL error, output it before the results
     */
    if (curl_errno($ch)) {
        print PHP_EOL . 'cURL error: ' . curl_error($ch) . PHP_EOL;
    }

    print PHP_EOL . 'test result:' . PHP_EOL;
    if ($results) {
        print 'Controller connection success' . PHP_EOL;
        die;
    }

    print 'Controller connection failed' . PHP_EOL;
} else {
    print PHP_EOL . 'ERROR: cURL could not be initialized!' . PHP_EOL;
}
