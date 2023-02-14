<?php

namespace AmiDev\WrpDistributor;

use phpseclib3\Crypt\PublicKeyLoader;
use phpseclib3\Net\SSH2;

class DockerManager
{
    private const DOCKER_IMAGE = 'tenox7/wrp';
    public const EXCEPTION_ALREADY_HAS_CONTAINER = 128;
    public const EXCEPTION_HAS_NO_CONTAINER = 256;

    public function __construct(
        private readonly ServiceContainer $serviceContainer,
        private array                     $containerHosts = [],
        private array                     $privateKeys = [],
    ) {
        [$this->containerHosts, $this->privateKeys] = $this->readConfiguredHosts();
    }

    public function startContainer(Session $session): void
    {
        if (null === $session->id) {
            throw new \LogicException('Session not persisted yet!');
        }

        if (null !== $session->wrpContainerId) {
            throw new \LogicException(
                sprintf(
                    'Session %d already has container %s on host %s attached!',
                    $session->id,
                    $session->wrpContainerId,
                    $session->containerHost
                ),
                self::EXCEPTION_ALREADY_HAS_CONTAINER
            );
        }

        $randomHost = $this->getRandomHost();

        $this->serviceContainer->logger->debug(
            'startContainer() determined new containerHost',
            [
                'randomHost' => $randomHost['host'],
                'privateKey' => $randomHost['privateKey'],
            ]
        );

        $session->containerHost = $randomHost['host'];
        $session->port = $this->findUnusedPort();
        [$userName, $privateKey] = $randomHost['privateKey'];

        $key = PublicKeyLoader::load(file_get_contents('ssh/' . $privateKey));
        $ssh = new SSH2($session->containerHost);
        if (!$ssh->login($userName, $key)) {
            $this->serviceContainer->logger->error('startContainer() failed to SSH into the containerHost');

            throw new \RuntimeException('Can\'t login to containerHost! Configuration issue?');
        }

        $containerStartCommand = sprintf(
            "docker run --rm --name %s -d -p %d:8080 %s",
            "wrp_session_$session->id",
            $session->port,
            self::DOCKER_IMAGE
        );

        if (!$ssh->exec($containerStartCommand, function($shellOutput) use ($session) {
            $shellOutput = trim($shellOutput);
            $session->wrpContainerId = $shellOutput;
            $session->upsert();

            $this->serviceContainer->logger->info(
                'startContainer() successfully spun up new container',
                [
                    'containerId' => $session->wrpContainerId,
                    'shellOutput' => $shellOutput,
                ]
            );
        })) {
            $this->serviceContainer->logger->info(
                'startContainer() failed to spin up new container',
                [
                    'containerId' => $session->wrpContainerId,
                ]
            );

            throw new \RuntimeException("Can't start container on determined host!");
        }
    }

    public function stopContainer(Session $session): void
    {
        if (null === $session->id) {
            throw new \LogicException('A not yet persisted session for sure has no container!');
        }

        if (null === $session->wrpContainerId) {
            throw new \LogicException(
                sprintf(
                    'Session %d has no container attached! I would suggest calling startContainer() before, but since you wanted to stop it... lol...',
                    $session->id
                ),
                self::EXCEPTION_HAS_NO_CONTAINER
            );
        }

        $hostIndex = array_search($session->containerHost, $this->containerHosts, true);
        $containerHost = $this->containerHosts[$hostIndex];
        [$userName, $privateKey] = $this->privateKeys[$hostIndex];

        $key = PublicKeyLoader::load(file_get_contents('ssh/' . $privateKey));
        $ssh = new SSH2($containerHost);
        if (!$ssh->login($userName, $key)) {
            throw new \RuntimeException('Can\'t login to containerHost! Configuration issue?');
        }

        $containerStopCommand = sprintf(
            "docker stop %s",
            $session->wrpContainerId,
        );

        if (!$ssh->exec($containerStopCommand, function($shellOutput) use ($session) {
            $session->wrpContainerId = null;
            $session->port = null;
            $session->containerHost = null;

            $session->upsert();
        })) {
            throw new \RuntimeException("Can't stop container!");
        }
    }

    private function getRandomHost(): array
    {
        $randomHostIndex = array_rand($this->containerHosts);

        return [
            'host' => $this->containerHosts[$randomHostIndex],
            'privateKey' => $this->privateKeys[$randomHostIndex]
        ];
    }

    private function findUnusedPort(): int
    {
        $startPort = $_ENV['START_PORT'];
        $maxContainers = $_ENV['MAX_CONTAINERS_RUNNING'];

        $query = "SELECT MAX(`port`)+1 as 'nextPort' FROM `sessions`";
        if ($result = $this->serviceContainer->pdo->query($query)->fetch()) {
            if (empty($result['nextPort'])) {
                $this->serviceContainer->logger->debug(
                    'findUnusedPort() falling back to START_PORT because of empty result',
                    [
                        'nextPort' => $_ENV['START_PORT'],
                    ]
                );

                return $_ENV['START_PORT'];
            }

            if (($startPort + $maxContainers) < $result['nextPort']) {
                throw new \RuntimeException('No free ports left!');
            }

            $this->serviceContainer->logger->debug(
                'findUnusedPort() determined next port to use',
                [
                    'nextPort' => $result['nextPort'],
                ]
            );

            return $result['nextPort'];
        }

        $this->serviceContainer->logger->debug(
            'findUnusedPort() falling back to START_PORT at end of method',
            [
                'nextPort' => $_ENV['START_PORT'],
            ]
        );

        return $_ENV['START_PORT'];
    }


    private function readConfiguredHosts(): array
    {
        $containerHosts = explode(',', $_ENV['CONTAINER_HOSTS']);
        $containerHostKeys = explode(',', $_ENV['CONTAINER_HOSTS_KEYS']);
        foreach ($containerHostKeys as $key => $containerHostKey) {
            $containerHostKeys[$key] = explode('~', $containerHostKey);
        }

        $this->serviceContainer->logger->debug(
            "readConfiguredHosts()",
            [
                'hosts' => $containerHosts,
                'keys' => $containerHostKeys,
                'env' => $_ENV,
            ]
        );

        return [
            $containerHosts,
            $containerHostKeys,
        ];
    }
}