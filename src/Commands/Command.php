<?php

declare(strict_types = 1);

namespace AmiDev\WrpDistributor\Commands;

use AmiDev\WrpDistributor\Logger;
use AmiDev\WrpDistributor\ServiceContainer;
use AmiDev\WrpDistributor\Session;
use AmiDev\WrpDistributor\Statistics;
use Dotenv\Dotenv;
use Dotenv\Exception\InvalidEncodingException;
use Dotenv\Exception\InvalidFileException;
use Dotenv\Exception\InvalidPathException;
use Symfony\Component\Console\Command\Command as SymfonyConsoleCommand;
use Symfony\Component\Console\Exception\LogicException;

final class Command extends SymfonyConsoleCommand
{
    protected ServiceContainer $serviceContainer;

    /**
     * @throws LogicException
     * @throws InvalidPathException
     * @throws InvalidEncodingException
     * @throws InvalidFileException
     */
    public function __construct()
    {
        parent::__construct();

        $environmentVarsToLoad = [
            'MAX_CONTAINERS_RUNNING',
            'CONTAINER_HOSTS',
            'CONTAINER_HOSTS_KEYS',
            'CONTAINER_HOSTS_TLS_CERTS',
            'CONTAINER_DISTRIBUTION_METHOD',
            'SESSION_DATABASE_DSN',
            'SESSION_DATABASE_USER',
            'SESSION_DATABASE_PASS',
            'START_PORT',
            'AUTH_TOKEN',
        ];

        $dotenv = Dotenv::createImmutable(__DIR__ . DIRECTORY_SEPARATOR . '..' . DIRECTORY_SEPARATOR . '..' . DIRECTORY_SEPARATOR);
        $dotenv->load();
        $dotenv->required($environmentVarsToLoad);

        $pdo = new \PDO($_ENV['SESSION_DATABASE_DSN'], $_ENV['SESSION_DATABASE_USER'], $_ENV['SESSION_DATABASE_PASS']);
        $logger = new Logger();

        try {
            $this->serviceContainer = new ServiceContainer(
                $logger,
                $pdo,
            );

            Session::createSessionTableIfNotExisting($this->serviceContainer);
            Statistics::createStatisticsTableIfNotExisting($this->serviceContainer);
        } catch (\Throwable) {
            $logger->error('Startup error: invalid container host configuration!');

            exit(self::FAILURE);
        }
    }
}
