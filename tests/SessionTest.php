<?php

declare(strict_types = 1);

namespace Tests;

use AmiDev\WrpDistributor\Session;

final class SessionTest extends BaseTestCase
{
    public function testSessionCanBeCreatedFromHttpData(): void
    {
        $session = Session::create(
            $this->serviceContainer,
            '1.2.3.4',
            'userAgent',
        );

        self::assertSame('1.2.3.4', $session->clientIp);
        self::assertSame('userAgent', $session->clientUserAgent);
    }

    public function testNewSessionCanBeUpserted(): void
    {
        $pdo = $this->createMock(\PDO::class);

        $statement = $this->createMock(\PDOStatement::class);
        $statement
            ->expects(self::once())
            ->method('execute')
            ->willReturn(true)
        ;

        $pdo
            ->expects(self::once())
            ->method('prepare')
            ->willReturn($statement)
        ;

        $pdo
            ->expects(self::once())
            ->method('lastInsertId')
            ->willReturn((string) mt_rand())
        ;

        $serviceContainer = clone $this->serviceContainer;
        $serviceContainer->pdo = $pdo;

        $session = Session::create(
            $serviceContainer,
            '1.2.3.4',
            'userAgent',
        );
        $session->upsert();

        self::assertNotNull($session->id);
    }

    public function testExistingSessionCanBeUpserted(): void
    {
        $pdo = $this->createMock(\PDO::class);

        $statement = $this->createMock(\PDOStatement::class);
        $statement
            ->expects(self::exactly(2))
            ->method('execute')
            ->willReturn(true)
        ;

        $pdo
            ->expects(self::exactly(2))
            ->method('prepare')
            ->willReturn($statement)
        ;

        $pdo
            ->expects(self::once())
            ->method('lastInsertId')
            ->willReturn((string) mt_rand())
        ;

        $serviceContainer = clone $this->serviceContainer;
        $serviceContainer->pdo = $pdo;

        $session = Session::create(
            $serviceContainer,
            '1.2.3.4',
            'userAgent',
        );
        $session->upsert();

        self::assertNotNull($session->id);

        $idAfterCreate = $session->id;
        $lastUsedAfterCreate = $session->lastUsed;

        $session->upsert();
        self::assertNotSame($lastUsedAfterCreate, $session->lastUsed);
        self::assertSame($idAfterCreate, $session->id);
    }

    public function testSessionCanBeDeleted(): void
    {
        $pdo = $this->createMock(\PDO::class);

        $statement = $this->createMock(\PDOStatement::class);
        $statement
            ->expects(self::exactly(2))
            ->method('execute')
            ->willReturn(true)
        ;

        $pdo
            ->expects(self::exactly(2))
            ->method('prepare')
            ->willReturn($statement)
        ;

        $pdo
            ->expects(self::once())
            ->method('lastInsertId')
            ->willReturn((string) mt_rand())
        ;

        $serviceContainer = clone $this->serviceContainer;
        $serviceContainer->pdo = $pdo;

        $session = Session::create(
            $serviceContainer,
            '1.2.3.4',
            'userAgent',
        );
        $session->upsert();

        self::assertNotNull($session->id);
        $session->delete();
    }

    public function testSessionCanBeLoadedFromDatabase(): void
    {
        $pdo = $this->createMock(\PDO::class);

        $statement = $this->createMock(\PDOStatement::class);
        $statement
            ->expects(self::once())
            ->method('execute')
            ->willReturn(true)
        ;

        $statement
            ->expects(self::once())
            ->method('fetch')
            ->willReturn([
                'clientIp' => '1.2.3.4',
                'clientUserAgent' => 'userAgent',
                'id' => mt_rand(),
                'wrpContainerId' => sha1(microtime()),
                'containerHost' => 'hostname.tld',
                'port' => random_int(1000, 64000),
                'started' => (new \DateTime('yesterday'))->format('c'),
                'lastUsed' => (new \DateTime())->format('c'),
            ])
        ;

        $pdo
            ->expects(self::once())
            ->method('prepare')
            ->willReturn($statement)
        ;

        $serviceContainer = clone $this->serviceContainer;
        $serviceContainer->pdo = $pdo;

        $session = Session::loadFromDatabase(
            $serviceContainer,
            '1.2.3.4',
            'userAgent',
        );

        self::assertNotNull($session->id);
    }

    public function testSessionCanBeLoadedByIdFromDatabase(): void
    {
        $pdo = $this->createMock(\PDO::class);

        $statement = $this->createMock(\PDOStatement::class);
        $statement
            ->expects(self::once())
            ->method('execute')
            ->with(['sessionId' => 42])
            ->willReturn(true)
        ;

        $statement
            ->expects(self::once())
            ->method('fetch')
            ->willReturn([
                'clientIp' => '1.2.3.4',
                'clientUserAgent' => 'userAgent',
                'id' => 42,
                'wrpContainerId' => sha1(microtime()),
                'containerHost' => 'hostname.tld',
                'port' => random_int(1000, 64000),
                'started' => (new \DateTime('yesterday'))->format('c'),
                'lastUsed' => (new \DateTime())->format('c'),
            ])
        ;

        $pdo
            ->expects(self::once())
            ->method('prepare')
            ->willReturn($statement)
        ;

        $serviceContainer = clone $this->serviceContainer;
        $serviceContainer->pdo = $pdo;

        $session = Session::loadFromDatabaseById(
            $serviceContainer,
            42,
        );

        self::assertNotNull($session->id);
    }

    public function testCountAllSessions(): void
    {
        $pdo = $this->createMock(\PDO::class);

        $statement = $this->createMock(\PDOStatement::class);

        $statement
            ->expects(self::once())
            ->method('fetch')
            ->willReturn([1337])
        ;

        $pdo
            ->expects(self::once())
            ->method('query')
            ->willReturn($statement)
        ;

        $serviceContainer = clone $this->serviceContainer;
        $serviceContainer->pdo = $pdo;

        $session = Session::create(
            $serviceContainer,
            '1.2.3.4',
            'userAgent',
        );

        $session->countAllSessions();
    }

    public function testGenerateContainerToken()
    {
        function sprintf(string $a, ...$values)
        {
            return 'totallyNotAnSha1';
        }

        $session = Session::create(
            $this->serviceContainer,
            '1.2.3.4',
            'userAgent',
        );

        $token = $session->generateContainerAuthToken();
        self::assertNotSame('totallyNotAnSha1', $token);
    }
}
