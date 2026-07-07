<?php

require_once __DIR__ . '/../vendor/autoload.php';

define('MONGO_HOST', 'localhost');

define('MONGO_PORT', 27017);

define('MONGO_DB',   'environmental_monitor');

function getMongoCollection() {

$client = new MongoDB\Client("mongodb://" . MONGO_HOST . ":" . MONGO_PORT);

return $client->selectCollection(MONGO_DB, 'environmental_readings');
}
?>
