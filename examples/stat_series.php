<?php
/**
 * PHP API usage example
 *
 * contributed by: Art of WiFi
 * description: example to pull WAN and wireless statistics from a controller, using the legacy report endpoints
 *              on Network Application 10.x and older and the new statistics engine (stat_series()) on 11.x,
 *              selected through get_stats_source().
 */
use UniFi_API\Exceptions\JsonDecodeException;
use UniFi_API\Exceptions\UnifiApiExceptionInterface;

/**
 * using the composer autoloader
 */
require_once 'vendor/autoload.php';

/**
 * Include the config file (place your credentials etc. there if not already present), see the config.template.php
 * file for an example.
 *
 * @var string $controlleruser
 * @var string $controllerpassword
 * @var string $controllerurl
 * @var string $controllerversion
 * @var bool $debug
 */
require_once 'config.php';

/**
 * the site to use
 */
$site_id = '<enter your site id here>';

try {
    $unifi_connection = new UniFi_API\Client(
        $controlleruser,
        $controllerpassword,
        $controllerurl,
        $site_id,
        $controllerversion
    );

    $unifi_connection->set_debug($debug);
    $unifi_connection->login();

    /**
     * detect the statistics engine: 'MODERN' on Network Application 11.x, 'LEGACY' on 10.x,
     * controllers older than 10.x do not have the endpoint at all
     */
    try {
        $stats_source = $unifi_connection->get_stats_source();
    } catch (JsonDecodeException $e) {
        $stats_source = 'LEGACY';
    }

    echo 'statistics source: ' . $stats_source . PHP_EOL;

    if ($stats_source === 'MODERN') {
        /**
         * the series endpoint keys on the site _id, not on the short site name
         */
        $site_uid = null;

        foreach ($unifi_connection->list_sites() as $site) {
            if ($site->name === $site_id) {
                $site_uid = $site->_id;
            }
        }

        /**
         * WAN traffic for the past 24 hours in 5-minute buckets, only the two byte counters,
         * with a bucket for every interval even when no data was recorded
         */
        $wan = $unifi_connection->stat_series(
            'wanBySite',
            'DAY',
            null,
            null,
            "siteId.eq('$site_uid')",
            'values(rxBytes,txBytes)',
            true
        );

        $rx = 0;
        $tx = 0;

        foreach ($wan[0]->series ?? [] as $bucket) {
            $rx += $bucket->rxBytes ?? 0;
            $tx += $bucket->txBytes ?? 0;
        }

        printf("WAN last 24h: %.1f MiB received, %.1f MiB transmitted (%d buckets)%s", $rx / 1048576, $tx / 1048576, count($wan[0]->series ?? []), PHP_EOL);

        /**
         * wireless client counts and traffic per SSID for every access point, past hour
         */
        $vaps = $unifi_connection->stat_series(
            'wirelessAnalyticsBySiteByDevice',
            'HOUR',
            null,
            null,
            "siteId.eq('$site_uid')",
            'nested(byVap(values(avgClientCount,rxBytes,txBytes)))'
        );

        echo json_encode($vaps, JSON_PRETTY_PRINT) . PHP_EOL;
    } else {
        /**
         * the legacy report endpoints, populated on Network Application 10.x and older
         */
        $hourly = $unifi_connection->stat_hourly_site();

        echo json_encode($hourly, JSON_PRETTY_PRINT) . PHP_EOL;
    }
} catch (UnifiApiExceptionInterface $e) {
    echo get_class($e) . ': ' . $e->getMessage() . PHP_EOL;
}
