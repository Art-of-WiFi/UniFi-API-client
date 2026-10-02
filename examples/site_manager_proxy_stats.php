<?php
/**
 * PHP API usage example
 *
 * contributed by: Art of WiFi
 * description:    example to demonstrate Site Manager proxy mode, pulling daily site stats
 *                 from a remote UniFi OS console through the Ubiquiti Site Manager cloud proxy
 *
 * note:           Site Manager proxy mode is stateless — no login() or logout() calls are needed.
 *                 All API requests are routed through api.ui.com to the target console.
 *                 A Site Manager API key (not a local controller API key) is required.
 */
use UniFi_API\Exceptions\ConsoleOfflineException;
use UniFi_API\Exceptions\CurlExtensionNotLoadedException;
use UniFi_API\Exceptions\CurlGeneralErrorException;
use UniFi_API\Exceptions\CurlTimeoutException;
use UniFi_API\Exceptions\InvalidArgumentException;
use UniFi_API\Exceptions\InvalidSiteNameException;
use UniFi_API\Exceptions\JsonDecodeException;
use UniFi_API\Exceptions\LoginFailedException;

/**
 * using the composer autoloader
 */
require_once 'vendor/autoload.php';

/**
 * Site Manager proxy configuration
 *
 * The console ID (host ID) is visible in the URL when managing a console via unifi.ui.com:
 * https://unifi.ui.com/consoles/{console_id}/network/default/dashboard
 *
 * Generate a Site Manager API key at https://unifi.ui.com under Account > API Keys.
 * This is NOT the same as a local controller API key.
 */
$console_id          = '<enter your console id here>';
$site_manager_api_key = '<enter your site manager api key here>';

/**
 * the short name of the site you wish to query
 */
$site_id = '<enter your site id here>';

/**
 * create the client via the static factory method and pull daily site stats
 */
try {
    $unifi_connection = UniFi_API\Client::connect_via_site_manager(
        $console_id,
        $site_manager_api_key,
        $site_id
    );

    /**
     * pull daily site stats for the last 7 days
     */
    $start = (time() - 7 * 86400) * 1000;
    $end   = (time() - (time() % 3600)) * 1000;

    $results = $unifi_connection->stat_daily_site($start, $end);

    /**
     * output the results in correct json formatting
     */
    header('Content-Type: application/json');
    echo json_encode($results, JSON_PRETTY_PRINT);
} catch (CurlExtensionNotLoadedException $e) {
    echo 'CurlExtensionNotLoadedException: ' . $e->getMessage() . PHP_EOL;
} catch (InvalidSiteNameException $e) {
    echo 'InvalidSiteNameException: ' . $e->getMessage() . PHP_EOL;
} catch (JsonDecodeException $e) {
    echo 'JsonDecodeException: ' . $e->getMessage() . PHP_EOL;
} catch (ConsoleOfflineException $e) {
    echo 'ConsoleOfflineException: ' . $e->getMessage() . PHP_EOL;
} catch (LoginFailedException $e) {
    echo 'LoginFailedException: ' . $e->getMessage() . PHP_EOL;
} catch (CurlGeneralErrorException $e) {
    echo 'CurlGeneralErrorException: ' . $e->getMessage() . PHP_EOL;
} catch (CurlTimeoutException $e) {
    echo 'CurlTimeoutException: ' . $e->getMessage() . PHP_EOL;
} catch (InvalidArgumentException $e) {
    echo 'InvalidArgumentException: ' . $e->getMessage() . PHP_EOL;
} catch (Exception $e) {
    /** catch any other Exceptions that might be thrown */
    echo 'General Exception: ' . $e->getMessage() . PHP_EOL;
}
