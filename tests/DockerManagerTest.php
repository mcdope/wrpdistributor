<?php

declare(strict_types = 1);

namespace Tests;

use AmiDev\WrpDistributor\DockerManager;
use AmiDev\WrpDistributor\Exceptions\Docker\HostConfigurationMismatchException;

/**
 * @backupGlobals enabled
 */
final class DockerManagerTest extends BaseTestCase
{
    public function testCountSessionsPerContainerHost(): void
    {
        $serviceContainer = clone $this->serviceContainer;

        $statement = $this->createMock(\PDOStatement::class);
        $statement
            ->expects(self::once())
            ->method('fetchAll')
            ->willReturn([
                [
                    'containerHost' => 'host1.tld',
                    'count' => 20,
                ],
                [
                    'containerHost' => 'host2.tld',
                    'count' => 20,
                ],
            ])
        ;

        $pdo = $this->createMock(\PDO::class);
        $pdo
            ->expects(self::once())
            ->method('query')
            ->willReturn($statement)
        ;

        $serviceContainer->pdo = $pdo;

        $_ENV['CONTAINER_HOSTS'] .= ',' . $_ENV['CONTAINER_HOSTS'];
        $_ENV['MAX_CONTAINERS_RUNNING'] .= ',' . $_ENV['MAX_CONTAINERS_RUNNING'];
        $_ENV['CONTAINER_HOSTS_KEYS'] .= ',' . $_ENV['CONTAINER_HOSTS_KEYS'];
        $_ENV['CONTAINER_HOSTS_TLS_CERTS'] .= ',' . $_ENV['CONTAINER_HOSTS_TLS_CERTS'];
        $serviceContainer->dockerManager = new DockerManager($serviceContainer);

        $sessionsPerHost = $serviceContainer->dockerManager->countSessionsPerContainerHost();

        self::assertIsArray($sessionsPerHost);
        foreach ($sessionsPerHost as $host => $value) {
            switch ($host) {
                case 'host2.tld':
                case 'host1.tld':
                    self::assertSame(20, (int) $value);
                    break;
                default:
                    self::assertSame(0, (int) $value);
            }

            self::assertIsString($host);
            self::assertIsNumeric($value);
        }
    }

    public function testFindUnusedPort(): void
    {
        $serviceContainer = clone $this->serviceContainer;

        $statement = $this->createMock(\PDOStatement::class);
        $statement
            ->expects(self::exactly(4))
            ->method('fetch')
            ->willReturnOnConsecutiveCalls(
                ['nextPort' => $_ENV['START_PORT']],
                ['nextPort' => null],
                ['nextPort' => 65535],
                false,
            )
        ;

        $pdo = $this->createMock(\PDO::class);
        $pdo
            ->expects(self::exactly(4))
            ->method('query')
            ->willReturn($statement)
        ;

        $serviceContainer->pdo = $pdo;
        $serviceContainer->dockerManager = new DockerManager($serviceContainer);

        $r = new \ReflectionClass($serviceContainer->dockerManager);
        $m = $r->getMethod('findUnusedPort');

        for ($i = 0; $i <= 3; ++$i) {
            try {
                $nextPort = $m->invoke($serviceContainer->dockerManager, '');
            } catch (\Throwable $t) {
                self::assertSame(
                    'No free ports left!',
                    $t->getMessage(),
                );
            }

            self::assertGreaterThanOrEqual($_ENV['START_PORT'], $nextPort);
            self::assertLessThan(65535, $nextPort);
        }
    }

    public function testReadConfiguredHosts(): void
    {
        $serviceContainer = clone $this->serviceContainer;

        $this->expectException(HostConfigurationMismatchException::class);
        $oldEnv = $_ENV;
        $_ENV['CONTAINER_HOSTS'] .= ',' . $_ENV['CONTAINER_HOSTS'];

        // tested indirect, called in __construct
        $serviceContainer->dockerManager = new DockerManager($serviceContainer);
        $_ENV = $oldEnv;
    }

    public function testIsContainerIdValid(): void
    {
        $serviceContainer = clone $this->serviceContainer;
        $serviceContainer->dockerManager = new DockerManager($serviceContainer);

        $r = new \ReflectionClass($serviceContainer->dockerManager);
        $m = $r->getMethod('isContainerIdValid');

        self::assertFalse($m->invoke($serviceContainer->dockerManager, 'notAnId'));
        self::assertTrue($m->invoke($serviceContainer->dockerManager, 'acdea168264a08f9aaca0dfc82ff3551418dfd22d02b713142a6843caa2f61bf'));
    }
}
