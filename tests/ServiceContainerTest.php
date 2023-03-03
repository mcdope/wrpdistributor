<?php

namespace Tests;

use AmiDev\WrpDistributor\Logger;
use AmiDev\WrpDistributor\ServiceContainer;
use AmiDev\WrpDistributor\Session;
use Monolog\Handler\RotatingFileHandler;
use Psr\Log\LoggerInterface;

final class ServiceContainerTest extends BaseTestCase {
    public function testCanBeConstructed()
    {
        $serviceContainer = new ServiceContainer(
            $this->createMock(Logger::class),
            $this->createMock(\PDO::class),
        );

        self::assertNotNull($serviceContainer->dockerManager);
        self::assertNotNull($serviceContainer->statistics);
    }
}