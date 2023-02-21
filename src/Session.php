<?php

namespace AmiDev\WrpDistributor;

class Session
{
    public string $clientIp;
    public string $clientUserAgent;
    public ?int $id = null;
    public ?string $wrpContainerId;
    public ?string $containerHost = null;
    public ?int $port = null;
    public ?\DateTime $started = null;
    public ?\DateTime $lastUsed = null;

    public ?string $authToken = null;

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
        ?\DateTime $lastUsed = null,
        ?string $authToken = null
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
        $this->authToken = $authToken;
    }

    /**
     * @throws \RuntimeException
     */
    public function upsert(): void
    {
        $this->lastUsed = new \DateTime();

        if (null === $this->id) {
            $query = "INSERT INTO `sessions` (
                            clientIp, 
                            clientUserAgent,
                            wrpContainerId,
                            containerHost,
                            port,
                            started,
                            token
                      ) VALUES (
                            :clientIp,
                            :clientUserAgent,
                            :wrpContainerId,
                            :containerHost,
                            :port,
                            :started,
                            :token
                      )";

            $parameters = [
                'clientIp' => $this->clientIp,
                'clientUserAgent' => $this->clientUserAgent,
                'wrpContainerId' => $this->wrpContainerId,
                'containerHost' => $this->containerHost,
                'port' => $this->port,
                'started' => $this->started?->format('c'),
                'token' => $this->authToken,
            ];
        } else {
            $query = "UPDATE `sessions` SET
                            wrpContainerId = :wrpContainerId,
                            containerHost = :containerHost,
                            port = :port,
                            lastUsed = :lastUsed
                      WHERE `id` = :id";

            $parameters = [
                'wrpContainerId' => $this->wrpContainerId,
                'containerHost' => $this->containerHost,
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

            throw new \RuntimeException(
                'Can\'t upsert session! PDO Error: ' . $this->serviceContainer->pdo->errorCode()
            );
        }

        if (null === $this->id) {
            $id = $this->serviceContainer->pdo->lastInsertId();

            if (false === $id) {
                throw new \RuntimeException('Couldn\'t insert session into database!');
            }

            $this->id = (int) $id;
        }
    }

    /**
     * @throws \LogicException
     */
    public function delete(): void
    {
        if (null === $this->id) {
            throw new \LogicException('Session not persisted yet!');
        }

        $this->serviceContainer->pdo
            ->prepare("DELETE FROM `sessions` WHERE id = :id")
            ->execute(['id' => $this->id]);
    }

    public static function create(
        ServiceContainer $serviceContainer,
        string $clientIp,
        string $clientUserAgent
    ): self {
        return new self(
            $serviceContainer,
            $clientIp,
            $clientUserAgent,
        );
    }

    /**
     * @throws \Exception
     */
    public static function loadFromDatabase(
        ServiceContainer $serviceContainer,
        string $clientIp,
        string $clientUserAgent
    ): self {
        $sessionDataStmt = $serviceContainer->pdo->prepare(
            "SELECT * FROM `sessions` WHERE clientIp = :clientIp AND clientUserAgent = :clientUserAgent"
        );

        if (!$sessionDataStmt
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
            (string) $sessionData['clientIp'],
            (string) $sessionData['clientUserAgent'],
            (int) $sessionData['id'],
            !empty($sessionData['wrpContainerId']) ? (string) $sessionData['wrpContainerId'] : null,
            !empty($sessionData['containerHost']) ? (string) $sessionData['containerHost'] : null,
            !empty($sessionData['port']) ? (int) $sessionData['port'] : null,
            new \DateTime($sessionData['started']),
            new \DateTime($sessionData['lastUsed'] ?? 'now'),
        );
    }

    /**
     * @throws \Exception
     */
    public static function loadFromDatabaseById(
        ServiceContainer $serviceContainer,
        int $sessionId
    ): self {
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
            (string) $sessionData['clientIp'],
            (string) $sessionData['clientUserAgent'],
            (int) $sessionData['id'],
            (string) $sessionData['wrpContainerId'],
            (string) $sessionData['containerHost'],
            (int) $sessionData['port'],
            new \DateTime($sessionData['started']),
            new \DateTime($sessionData['lastUsed'] ?? 'now'),
        );
    }

    // @todo: replace with migration
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

    public function countAllSessions(): int
    {
        return $this->serviceContainer->pdo->query('SELECT COUNT(`id`) FROM `sessions`')->fetch()[0];
    }

    /**
     * @throws \Exception
     */
    public function generateContainerAuthToken(): string
    {
        $tokenSourceString = sprintf(
            '%s_%d_%d_%s_%s_%s',
            (new \DateTime('now'))->format('c'),
            time(),
            $this->id,
            $this->clientIp,
            $this->clientUserAgent,
            bin2hex(random_bytes(2048))
        );

        return sha1($tokenSourceString);
    }
}
