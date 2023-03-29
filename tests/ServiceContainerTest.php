<?php

declare(strict_types = 1);

namespace Tests;

use AmiDev\WrpDistributor\Logger;
use AmiDev\WrpDistributor\ServiceContainer;

final class ServiceContainerTest extends BaseTestCase
{
    public function testCanBeConstructed(): void
    {
        $serviceContainer = new ServiceContainer(
            $this->createMock(Logger::class),
            $this->createMock(\PDO::class),
        );

        self::assertNotNull($serviceContainer->dockerManager);
        self::assertNotNull($serviceContainer->statistics);
    }
}
