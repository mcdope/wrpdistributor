<?php
require 'vendor/autoload.php';

use AmiDev\WrpDistributor\Actions\ActionInterface;
use AmiDev\WrpDistributor\Actions\DeadOrAlive;
use AmiDev\WrpDistributor\Actions\ExtendSession;
use AmiDev\WrpDistributor\Actions\StartSession;
use AmiDev\WrpDistributor\Actions\StopSession;
use AmiDev\WrpDistributor\Logger;
use AmiDev\WrpDistributor\ServiceContainer;
use AmiDev\WrpDistributor\Session;
use AmiDev\WrpDistributor\Statistics;

// Init stuff
$environmentVarsToLoad = [
    'MAX_CONTAINERS_RUNNING',
    'CONTAINER_HOSTS',
    'CONTAINER_HOSTS_KEYS',
    'CONTAINER_DISTRIBUTION_METHOD',
    'SESSION_DATABASE_DSN',
    'SESSION_DATABASE_USER',
    'SESSION_DATABASE_PASS',
    'START_PORT',
    'AUTH_TOKEN'
];

$dotenv = Dotenv\Dotenv::createImmutable(__DIR__);
$dotenv->load();
$dotenv->required($environmentVarsToLoad);

if (!isset($_SERVER['HTTP_BEARER']) || $_ENV['AUTH_TOKEN'] !== $_SERVER['HTTP_BEARER']) {
    http_response_code(401);
    return;
}

$pdo = new \PDO($_ENV['SESSION_DATABASE_DSN'], $_ENV['SESSION_DATABASE_USER'], $_ENV['SESSION_DATABASE_PASS']);
$logger = new Logger();
try {
    $serviceContainer = new ServiceContainer(
        $logger,
        $pdo,
    );

    Statistics::createStatisticsTableIfNotExisting($serviceContainer);
} catch (\Throwable $throwable) {
    $logger->error('Startup error: invalid container host configuration!');

    http_response_code(500);
    echo sprintf(
        '
            <h1>%s</h1>
            <p>%s</p>
            <p>
                <pre>%s</pre>
            </p>',
        'Startup error: invalid container host configuration!',
        $throwable->getMessage(),
        $throwable->getTraceAsString()
    );

    exit(1);
}

$currentClientIp = trim($_SERVER['REMOTE_ADDR']);
$currentClientUserAgent = trim($_SERVER['HTTP_USER_AGENT']);
// Load session if it exists, else create a new one.
Session::createSessionTableIfNotExisting($serviceContainer);
try {
    $session = Session::loadFromDatabase($serviceContainer, $currentClientIp, $currentClientUserAgent);
} catch (\Throwable $ex) {
    $session = Session::create($serviceContainer, $currentClientIp, $currentClientUserAgent);
}

// ifLoaded: update lastUsed, ifCreated: store
$session->upsert();

// Do the actual HTTP handling by using an action map
/** @var ActionInterface[] $actionMap */
$actionMap = [
    'DELETE' => new StopSession($serviceContainer),
    'PUT' => new StartSession($serviceContainer),
    'HEAD' => new ExtendSession($serviceContainer),
    'GET' => new DeadOrAlive($serviceContainer),
];

if (!array_key_exists($_SERVER['REQUEST_METHOD'], $actionMap)) {
    $serviceContainer->logger->warning(
        'Invalid request!',
        [
            'method' => $_SERVER['REQUEST_METHOD'],
            'request' => $_REQUEST
        ]
    );

    http_response_code(405);
    exit(1);
}

try {
    $actionMap[$_SERVER['REQUEST_METHOD']]($session);
    $logger->debug(
        'Successfully handled request',
        [
            'responseCode' => http_response_code(),
            'action' => $actionMap[$_SERVER['REQUEST_METHOD']]::class,
        ]
    );

    exit(0);
} catch (\LogicException $logicException) {
    $serviceContainer->logger->debug($logicException->getMessage(), $logicException->getTrace());

    http_response_code(400);
    echo sprintf(
        '
            <h1>%s</h1>
            <p>%s</p>
            <p>
                <pre>%s</pre>
            </p>',
        'Your request either makes no sense, or is invalid',
        $logicException->getMessage(),
        $logicException->getTraceAsString()
    );
}
