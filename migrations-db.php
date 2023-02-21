<?php

$environmentVarsToLoad = [
    'SESSION_DATABASE_DSN',
    'SESSION_DATABASE_USER',
    'SESSION_DATABASE_PASS',
];

$dotenv = Dotenv\Dotenv::createImmutable(__DIR__);
$dotenv->load();
$dotenv->required($environmentVarsToLoad);

return [
    'dbname' => 'wrpdistributor',
    'user' => $_ENV['SESSION_DATABASE_USER'],
    'password' => $_ENV['SESSION_DATABASE_PASS'],
    'host' => 'mysql_wrpdistributor',
    'driver' => 'pdo_mysql',
];