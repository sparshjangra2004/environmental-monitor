<?php

require_once __DIR__ . '/../vendor/autoload.php';

use Laudis\Neo4j\ClientBuilder;

define('NEO4J_URL',      'bolt://localhost:7687');

define('NEO4J_USER',     'neo4j');
define('NEO4J_PASSWORD', 'admin123');

function getNeoClient() {
    return ClientBuilder::create()
        ->withDriver('bolt', NEO4J_URL, \Laudis\Neo4j\Authentication\Authenticate::basic(NEO4J_USER, NEO4J_PASSWORD))
        ->withDefaultDriver('bolt')
        ->build();
}
?>
