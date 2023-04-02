<?php

$environmentVarsToLoad = [
    'SESSION_DATABASE_DSN',
    'SESSION_DATABASE_USER',
    'SESSION_DATABASE_PASS',
];

$dotenv = Dotenv\Dotenv::createImmutable(__DIR__);
$dotenv->load();
$dotenv->required($environmentVarsToLoad);

// SESSION_DATABASE_DSN=mysql:dbname=wrpdistributor;host=mysql_wrpdistributor
preg_match('/dbname=(.+);/', $_ENV['SESSION_DATABASE_DSN'],$matches_db);
$databaseName = $matches_db[1];

preg_match('/host=(.+)$/', $_ENV['SESSION_DATABASE_DSN'],$matches_host);
$databaseHost = $matches_host[1];

echo 'DEBUG THIS SHIT!!!!';
var_export($_ENV);

return [
    'dbname' => $databaseName,
    'user' => $_ENV['SESSION_DATABASE_USER'],
    'password' => $_ENV['SESSION_DATABASE_PASS'],
    'host' => $databaseHost,
    'driver' => 'pdo_mysql',
];