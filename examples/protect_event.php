<?php

/**
 * PHP API usage example
 *
 * contributed by: Art of WiFi
 * description:    example basic PHP script to list all site on the controller that are
 *                 available to the admin account defined in config.php
 */

/**
 * using the composer autoloader
 */
require_once '../vendor/autoload.php';

/**
 * include the config file (place your credentials etc. there if not already present)
 * see the config.template.php file for an example
 */
require_once 'config.php';

/**
 * we use the default site in the initial connection
 */
$site_id = 'default';

/**
 * initialize the UniFi API connection class and log in to the controller and do our thing
 */
$unifi_connection = new UniFi_API\ProtectClient($controlleruser, $controllerpassword, $controllerurl, $site_id, $controllerversion);
$set_debug_mode = $unifi_connection->set_debug($debug);
$loginresults = $unifi_connection->login();

// get events of last two hours
$data = $unifi_connection->getEvents(time()-2*3600, time(), 10);

/**
 * we can render the full results in json format
 */
//echo json_encode($data, JSON_PRETTY_PRINT);

/**
 * Get all cameras
 */
$cameraApiData = $unifi_connection->getCameras();

$cameras = [];
foreach ($cameraApiData as $camera) {
    $cameras[$camera->id] = $camera;
}

/**
 * or we print each event type and camera name
 */
$path = __DIR__ . '/test/';

if ((is_dir($path) === false) && !mkdir($path) && !is_dir($path)) {
    throw new \RuntimeException(sprintf('Directory "%s" was not created', $path));
}

foreach ($data as $event) {
    echo 'Event Type name: ' . $event->type . ', Kamera: ' . $cameras[$event->camera]->name . PHP_EOL;

    $dlPath = $path . $event->id . '.jpg';
    $unifi_connection->downloadEventThumbnail($dlPath, $event->id);

    $dlPath = $path . $event->id . '.mp4';
    $unifi_connection->downloadVideo($dlPath, $event->camera, $event->start, $event->end);
}

echo PHP_EOL;