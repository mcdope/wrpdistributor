<?php

namespace Tests;

use AmiDev\WrpDistributor\DockerManager;

final class DockerManagerTest extends BaseTestCase {
    public function testCountSessionsPerContainerHost()
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
            ]);

        $pdo = $this->createMock(\PDO::class);
        $pdo
            ->expects(self::once())
            ->method('query')
            ->willReturn($statement);

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
                    self::assertEquals(20, (int) $value);
                    break;
                default:
                    self::assertEquals(0, (int) $value);
            }

            self::assertIsString($host);
            self::assertIsNumeric($value);
        }
    }

    public function testGetMaxContainersPerHost()
    {
        $serviceContainer = clone $this->serviceContainer;

        $_ENV['CONTAINER_HOSTS'] .= ',' . 'testhost.tld';
        $_ENV['MAX_CONTAINERS_RUNNING'] .= ',' . '99';
        $_ENV['CONTAINER_HOSTS_KEYS'] .= ',' . '/dev/null~/dev/null';
        $_ENV['CONTAINER_HOSTS_TLS_CERTS'] .= ',' . '/dev/null~/dev/null';
        $serviceContainer->dockerManager = new DockerManager($serviceContainer);

        self::assertEquals(
            99,
            $serviceContainer->dockerManager->getMaxContainersForHost('testhost.tld')
        );
    }
}