<?php

namespace AmiDev\WrpDistributor\Commands;

use AmiDev\WrpDistributor\Logger;
use AmiDev\WrpDistributor\ServiceContainer;
use Dotenv\Dotenv;
use Symfony\Component\Console\Command\Command as SymfonyConsoleCommand;

class Command extends SymfonyConsoleCommand
{
    public function __construct(
        protected ?ServiceContainer $serviceContainer = null
    )
    {
        $environmentVarsToLoad = [
            'MAX_CONTAINERS_RUNNING',
            'CONTAINER_HOSTS',
            'CONTAINER_HOSTS_KEYS',
            'SESSION_DATABASE_DSN',
            'SESSION_DATABASE_USER',
            'SESSION_DATABASE_PASS',
            'START_PORT',
            'AUTH_TOKEN'
        ];

        $dotenv = Dotenv::createImmutable(__DIR__ . DIRECTORY_SEPARATOR . '..' . DIRECTORY_SEPARATOR . '..' . DIRECTORY_SEPARATOR);
        $dotenv->load();
        $dotenv->required($environmentVarsToLoad);

        $pdo = new \PDO($_ENV['SESSION_DATABASE_DSN'], $_ENV['SESSION_DATABASE_USER'], $_ENV['SESSION_DATABASE_PASS']);
        $logger = new Logger();
        $this->serviceContainer = new ServiceContainer(
            $logger,
            $pdo,
        );

        parent::__construct();
    }
}