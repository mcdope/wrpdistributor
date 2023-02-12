<?php

namespace AmiDev\WrpDistributor;

use phpseclib3\Crypt\PublicKeyLoader;
use phpseclib3\Net\SSH2;

class Session {
    public const EXCEPTION_ALREADY_HAS_CONTAINER = 128;
    public const EXCEPTION_HAS_NO_CONTAINER = 256;

    private const DOCKER_IMAGE = 'tenox7/wrp';

    public string $clientIp;
    public string $clientUserAgent;
    public ?int $id = null;
    public ?string $wrpContainerId;
    public ?string $containerHost = null;
    public ?int $port = null;
    public ?\DateTime $started = null;
    public ?\DateTime $lastUsed = null;

    private ServiceContainer $serviceContainer;

    private function __construct(
        ServiceContainer $serviceContainer,
         string $clientIp,
         string $clientUserAgent,
         ?int $id = null,
         ?string $wrpContainerId = null,
         ?string $containerHost = null,
         ?int $port = null,
         ?\DateTime $started = null,
         ?\DateTime $lastUsed = null
    ) {
        if (null === $started) {
            $this->started = new \DateTime();
        }

        $this->serviceContainer = $serviceContainer;

        $this->clientIp = $clientIp;
        $this->clientUserAgent = $clientUserAgent;
        $this->id = $id;
        $this->wrpContainerId = $wrpContainerId;
        $this->containerHost = $containerHost;
        $this->port = $port;
        $this->lastUsed = $lastUsed;
    }

    public function upsert(): bool
    {
        $this->lastUsed = new \DateTime();

        if (null === $this->id) {
            $query = "INSERT INTO `sessions` (
                            clientIp, 
                            clientUserAgent,
                            wrpContainerId,
                            port,
                            started
                      ) VALUES (
                            :clientIp,
                            :clientUserAgent,
                            :wrpContainerId,
                            :port,
                            :started
                      )";

            $parameters = [
                'clientIp' => $this->clientIp,
                'clientUserAgent' => $this->clientUserAgent,
                'wrpContainerId' => $this->wrpContainerId,
                'port' => $this->port,
                'started' => $this->started->format('c'),
            ];
        } else {
            $query = "UPDATE `sessions` SET
                            wrpContainerId = :wrpContainerId,
                            port = :port,
                            lastUsed = :lastUsed
                      WHERE `id` = :id";

            $parameters = [
                'wrpContainerId' => $this->wrpContainerId,
                'port' => $this->port,
                'lastUsed' => $this->lastUsed->format('c'),
                'id' => $this->id,
            ];
        }

        $upsertStatement = $this->serviceContainer->pdo->prepare($query);
        if (!$upsertStatement->execute($parameters)) {
            $this->serviceContainer->logger->error(
                'Session upsert failed',
                [
                    'query' => $upsertStatement->queryString,
                    'parameters' => $parameters,
                    'errorInfo' => $this->serviceContainer->pdo->errorInfo(),
                ]
            );

            throw new \Exception('Can\'t upsert session! PDO Error: ' . $this->serviceContainer->pdo->errorCode());
        }

        if (null === $this->id) {
            $this->id = $this->serviceContainer->pdo->lastInsertId();
        }

        return true;
    }

    public function delete(): bool
    {
        if (null === $this->id) {
            throw new \LogicException('Session not persisted yet!');
        }

        return $this->serviceContainer->pdo
            ->prepare("DELETE FROM `sessions` WHERE id = :id")
            ->execute(['id' => $this->id]);
    }

    public static function create(
        ServiceContainer $serviceContainer,
        string $clientIp,
        string $clientUserAgent
    ): self
    {
        return new self(
            $serviceContainer,
            $clientIp,
            $clientUserAgent,
        );
    }

    public static function loadFromDatabase(
        ServiceContainer $serviceContainer,
        string $clientIp,
        string $clientUserAgent
    ): self
    {
        $sessionDataStmt = $serviceContainer->pdo->prepare(
            "SELECT * FROM `sessions` WHERE clientIp = :clientIp AND clientUserAgent = :clientUserAgent"
        );

        if (
            !$sessionDataStmt
            ->execute([
                'clientIp' => $clientIp,
                'clientUserAgent' => $clientUserAgent,
            ])
        ) {
            throw new \LogicException('Could not load session from database! Most likely it does not exist yet.');
        }

        if (!$sessionData = $sessionDataStmt->fetch()) {
            throw new \LogicException('No existing session found');
        }

        return new self(
            $serviceContainer,
            $sessionData['clientIp'],
            $sessionData['clientUserAgent'],
            $sessionData['id'],
            $sessionData['wrpContainerId'],
            $sessionData['containerHost'],
            $sessionData['port'],
            new \DateTime($sessionData['started']),
            new \DateTime($sessionData['lastUsed'] ?? 'now'),
        );
    }

    public static function loadFromDatabaseById(
        ServiceContainer $serviceContainer,
        int $sessionId
    ): self
    {
        $sessionDataStmt = $serviceContainer->pdo->prepare(
            "SELECT * FROM `sessions` WHERE id = :sessionId"
        );

        if (!$sessionDataStmt->execute(['sessionId' => $sessionId])) {
            throw new \LogicException('Could not load session from database!');
        }

        if (!$sessionData = $sessionDataStmt->fetch()) {
            throw new \LogicException('Session not found by Id');
        }

        return new self(
            $serviceContainer,
            $sessionData['clientIp'],
            $sessionData['clientUserAgent'],
            $sessionData['id'],
            $sessionData['wrpContainerId'],
            $sessionData['containerHost'],
            $sessionData['port'],
            new \DateTime($sessionData['started']),
            new \DateTime($sessionData['lastUsed'] ?? 'now'),
        );
    }

    public function startContainer(): void
    {
        if (null === $this->id) {
            throw new \LogicException('Session not persisted yet!');
        }

        if (null !== $this->wrpContainerId) {
            throw new \LogicException(
                sprintf(
                    'Session %d already has container %s on host %s attached!',
                    $this->id, 
                    $this->wrpContainerId,
                    $this->containerHost
                ),
                self::EXCEPTION_ALREADY_HAS_CONTAINER
            );
        }

        $containerHosts = explode(',', $_ENV['CONTAINER_HOSTS']);
        $containerHostKeys = explode(',', $_ENV['CONTAINER_HOSTS_KEYS']);
        foreach ($containerHostKeys as $key => $containerHostKey) {
            $containerHostKeys[$key] = explode('~', $containerHostKey);
        }

        $randomHostIndex = array_rand($containerHosts);
        $randomHost = $containerHosts[$randomHostIndex];
        [$userName, $privateKey] = $containerHostKeys[$randomHostIndex];

        $this->serviceContainer->logger->debug(
            'startContainer() determined new containerHost',
            [
                'randomHostIndex' => $randomHostIndex,
                'randomHost' => $randomHost,
                'privateKey' => $privateKey,
            ]
        );

        $this->containerHost = $randomHost;
        $this->port = $this->findUnusedPort();

        $key = PublicKeyLoader::load(file_get_contents('ssh/' . $privateKey));
        $ssh = new SSH2($this->containerHost);
        if (!$ssh->login($userName, $key)) {
            $this->serviceContainer->logger->error('startContainer() failed to SSH into the containerHost');

            throw new \RuntimeException('Can\'t login to containerHost! Configuration issue?');
        }

        $containerStartCommand = sprintf(
            "docker run --rm --name %s -d -p %d:8080 %s",
            "wrp_session_{$this->id}",
            $this->port,
            self::DOCKER_IMAGE
        );

        if (!$ssh->exec($containerStartCommand, function($shellOutput) {
            $shellOutput = trim($shellOutput);
            $this->wrpContainerId = $shellOutput;
            $this->upsert();

            $this->serviceContainer->logger->info(
                'startContainer() successfully spun up new container',
                [
                    'containerId' => $this->wrpContainerId,
                    'shellOutput' => $shellOutput,
                ]
            );
        })) {
            $this->serviceContainer->logger->info(
                'startContainer() failed to spin up new container',
                [
                    'containerId' => $this->wrpContainerId,
                ]
            );

            throw new \RuntimeException("Can't start container on determined host!");
        }
    }

    public function stopContainer(): void
    {
        if (null === $this->id) {
            throw new \LogicException('A not yet persisted session for sure has no container!');
        }

        if (null === $this->wrpContainerId) {
            throw new \LogicException(
                sprintf(
                    'Session %d has no container attached! I would suggest calling startContainer() before, but since you wanted to stop it... lol...', 
                    $this->id
                ),
                self::EXCEPTION_HAS_NO_CONTAINER
            );
        }

        $containerHosts = explode(',', $_ENV['CONTAINER_HOSTS']);
        $containerHostKeys = explode(',', $_ENV['CONTAINER_HOSTS_KEYS']);
        foreach ($containerHostKeys as $key => $containerHostKey) {
            $containerHostKeys[$key] = explode('~', $containerHostKey);
        }

        $hostIndex = array_search($this->containerHost, $containerHosts, true);
        $containerHost = $containerHosts[$hostIndex];
        [$userName, $privateKey] = $containerHostKeys[$hostIndex];

        $key = PublicKeyLoader::load(file_get_contents('ssh/' . $privateKey));
        $ssh = new SSH2($containerHost);
        if (!$ssh->login($userName, $key)) {
            throw new \RuntimeException('Can\'t login to containerHost! Configuration issue?');
        }

        $containerStopCommand = sprintf(
            "docker stop %s",
            $this->wrpContainerId,
        );

        if (!$ssh->exec($containerStopCommand, function($shellOutput) {
            $this->wrpContainerId = null;
            $this->port = null;
            $this->containerHost = null;

            $this->upsert();
        })) {
            throw new \RuntimeException("Can't stop container!");
        }
    }

    private function findUnusedPort(): int
    {
        $startPort = $_ENV['START_PORT'];
        $maxContainers = $_ENV['MAX_CONTAINERS_RUNNING'];

        $query = "SELECT MAX(`port`)+1 as 'nextPort' FROM `sessions`";
        if ($result = $this->serviceContainer->pdo->query($query)->fetch()) {
            if (empty($result['nextPort'])) {
                $this->port = $_ENV['START_PORT'];

                $this->serviceContainer->logger->debug(
                    'findUnusedPort() falling back to START_PORT because of empty result',
                    [
                        'nextPort' => $_ENV['START_PORT'],
                    ]
                );

                return $this->port;
            }

            if (($startPort + $maxContainers) < $result['nextPort']) {
                throw new \Exception('No free ports left!');
            }

            $this->port = $result['nextPort'];

            $this->serviceContainer->logger->debug(
                'findUnusedPort() determined next port to use',
                [
                    'nextPort' => $this->port,
                ]
            );

            return $this->port;
        }

        $this->port = $_ENV['START_PORT'];
        $this->serviceContainer->logger->debug(
            'findUnusedPort() falling back to START_PORT at end of method',
            [
                'nextPort' => $_ENV['START_PORT'],
            ]
        );

        return $this->port;
    }

    public static function createSessionTableIfNotExisting(ServiceContainer $serviceContainer): void
    {
        $tableExists = $serviceContainer->pdo->query(
            "SELECT EXISTS (
                    SELECT TABLE_NAME FROM information_schema.TABLES 
                    WHERE TABLE_SCHEMA LIKE 'wrpdistributor' 
                      AND TABLE_TYPE LIKE 'BASE TABLE' 
                      AND TABLE_NAME = 'sessions'
               )"
        )->fetch();

        if (0 === (int)$tableExists[0]) {
            $serviceContainer->pdo->query(file_get_contents('db/sessions.sql'));
            $serviceContainer->logger->info(
                'Sessions table not found, created'
            );
        }
    }
}