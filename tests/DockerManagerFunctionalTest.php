<?php

declare(strict_types = 1);

namespace Tests;

use AmiDev\WrpDistributor\DockerManager;
use AmiDev\WrpDistributor\Logger;
use AmiDev\WrpDistributor\ServiceContainer;
use AmiDev\WrpDistributor\Session;

final class DockerManagerFunctionalTest extends BaseTestCase
{
    public function testStartContainerWithEqualStrategy(): void
    {
        $statementUpsert = $this->createMock(\PDOStatement::class);
        $statementUpsert
            ->expects(self::once())
            ->method('execute')
            ->willReturn(true)
        ;

        $statementPort = $this->createMock(\PDOStatement::class);
        $statementPort
            ->expects(self::once())
            ->method('fetch')
            ->willReturn(['nextPort' => 9567])
        ;

        $statement = $this->createMock(\PDOStatement::class);
        $statement
            ->expects(self::once())
            ->method('fetchAll')
            ->willReturn([
                [
                    'containerHost' => 'sshd_testing_wrpdistributor',
                    'count' => 2,
                ],
            ])
        ;

        $pdo = $this->createMock(\PDO::class);
        $pdo
            ->expects(self::exactly(2))
            ->method('query')
            ->willReturnOnConsecutiveCalls(
                $statement,
                $statementPort,
            )
        ;

        $pdo
            ->expects(self::once())
            ->method('prepare')
            ->willReturn($statementUpsert)
        ;

        $serviceContainer = new ServiceContainer(
            new Logger(),
            $pdo,
        );

        $session = Session::create(
            $serviceContainer,
            '1.2.3.4',
            'phpunitFunctionalTest',
        );
        $session->id = 42;

        $serviceContainer->pdo = $pdo;
        $serviceContainer->logger->debug('creating DockerManager');
        $_ENV['CONTAINER_DISTRIBUTION_METHOD'] = 'equal';
        $dockerManager = new DockerManager(
            $serviceContainer,
            ['sshd_testing_wrpdistributor'],
            [['phpunit', 'phpunit']],
            [99],
            [['/dev/null', '/dev/null']],
        );
        $serviceContainer->logger->debug('created DockerManager, calling startContainer');
        $dockerManager->startContainer($session);
        $serviceContainer->logger->debug('done');
    }

    public function testStartContainerWithFillhostStrategy(): void
    {
        $statementUpsert = $this->createMock(\PDOStatement::class);
        $statementUpsert
            ->expects(self::once())
            ->method('execute')
            ->willReturn(true)
        ;

        $statementPort = $this->createMock(\PDOStatement::class);
        $statementPort
            ->expects(self::once())
            ->method('fetch')
            ->willReturn(['nextPort' => 9567])
        ;

        $statement = $this->createMock(\PDOStatement::class);
        $statement
            ->expects(self::once())
            ->method('fetchAll')
            ->willReturn([
                [
                    'containerHost' => 'sshd_testing_wrpdistributor',
                    'count' => 2,
                ],
            ])
        ;

        $pdo = $this->createMock(\PDO::class);
        $pdo
            ->expects(self::exactly(2))
            ->method('query')
            ->willReturnOnConsecutiveCalls(
                $statement,
                $statementPort,
            )
        ;

        $pdo
            ->expects(self::once())
            ->method('prepare')
            ->willReturn($statementUpsert)
        ;

        $serviceContainer = new ServiceContainer(
            new Logger(),
            $pdo,
        );

        $session = Session::create(
            $serviceContainer,
            '1.2.3.4',
            'phpunitFunctionalTest',
        );
        $session->id = 42;

        $serviceContainer->pdo = $pdo;
        $serviceContainer->logger->debug('creating DockerManager');
        $_ENV['CONTAINER_DISTRIBUTION_METHOD'] = 'fillhost';
        $dockerManager = new DockerManager(
            $serviceContainer,
            ['sshd_testing_wrpdistributor'],
            [['phpunit', 'phpunit']],
            [99],
            [['/dev/null', '/dev/null']],
        );
        $serviceContainer->logger->debug('created DockerManager, calling startContainer');
        $dockerManager->startContainer($session);
        $serviceContainer->logger->debug('done');
    }

    public function testStopContainer(): void
    {
        $statementUpsert = $this->createMock(\PDOStatement::class);
        $statementUpsert
            ->expects(self::once())
            ->method('execute')
            ->willReturn(true)
        ;

        $pdo = $this->createMock(\PDO::class);
        $pdo
            ->expects(self::once())
            ->method('prepare')
            ->willReturn($statementUpsert)
        ;

        $serviceContainer = new ServiceContainer(
            new Logger(),
            $pdo,
        );

        $session = Session::create(
            $serviceContainer,
            '1.2.3.4',
            'phpunitFunctionalTest',
        );
        $session->id = 42;
        $session->containerHost = 'sshd_testing_wrpdistributor';
        $session->port = 1337;
        $session->wrpContainerId = 'acdea168264a08f9aaca0dfc82ff3551418dfd22d02b713142a6843caa2f61bf';

        $serviceContainer->pdo = $pdo;
        $serviceContainer->logger->debug('creating DockerManager');
        $dockerManager = new DockerManager(
            $serviceContainer,
            ['sshd_testing_wrpdistributor'],
            [['phpunit', 'phpunit']],
            [99],
            [['/dev/null', '/dev/null']],
        );
        $serviceContainer->logger->debug('created DockerManager, calling stopContainer');
        $dockerManager->stopContainer($session);
        $serviceContainer->logger->debug('done');
    }
}
