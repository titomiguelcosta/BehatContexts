<?php

use Doctrine\ORM\Tools\Console\ConsoleRunner;
use Doctrine\ORM\EntityManager;
use Doctrine\ORM\Tools\Setup;

require_once __DIR__ . '/../vendor/autoload.php';

$metadata = Setup::createAnnotationMetadataConfiguration([
    __DIR__ . '/../tests/Zorbus/Behat/Test/Entity'
]);
$entityManager = EntityManager::create([
    'driver' => 'pdo_mysql',
    'host' => 'localhost',
    'user' => 'root',
    'password' => null,
    'dbname' => 'behat_contexts'
], $metadata);

return ConsoleRunner::createHelperSet($entityManager);