<?php

declare(strict_types = 1);

namespace Tests;

use AmiDev\WrpDistributor\Logger;
use AmiDev\WrpDistributor\ServiceContainer;
use Dotenv\Dotenv;
use PHPUnit\Framework\TestCase;

abstract class BaseTestCase extends TestCase
{
    protected ServiceContainer $serviceContainer;

    public function __construct(string $name)
    {
        parent::__construct($name);

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

        $dotenv = Dotenv::createImmutable(__DIR__ . '/../', ['.env.test']);
        $dotenv->load();
        $dotenv->required($environmentVarsToLoad);

        $this->serviceContainer = new ServiceContainer(
            new Logger(),
            $this->createMock(\PDO::class),
        );
    }

    protected function tearDown(): void
    {
        unset($this->serviceContainer);
    }
}
