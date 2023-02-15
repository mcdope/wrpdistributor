<?php

namespace AmiDev\WrpDistributor;

use AmiDev\WrpDistributor\Exceptions\Docker\ContainerStartException;
use AmiDev\WrpDistributor\Exceptions\Docker\HostConfigurationMismatchException;
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

    public function countAvailableContainerHosts(): int
    {
        return \count($this->containerHosts);
    }

    public function countSessionsPerContainerHost(): array
    {
        return $this->serviceContainer->pdo->query("
            SELECT containerHost, COUNT(containerHost) as count
            FROM `sessions`
            WHERE containerHost IS NOT NULL
            GROUP BY containerHost
        ")->fetchAll();
    }

    public function countsPortsUsed(): int
    {
        return $this->serviceContainer->pdo->query('SELECT COUNT(`port`) FROM `sessions`')->fetch()[0];
    }

    /**
     * @throws ContainerStartException
     */
    public function startContainer(Session $session): void
    {
        if (null === $session->id) {
            throw new ContainerStartException('Session not persisted yet!');
        }

        if (null !== $session->wrpContainerId) {
            throw new ContainerStartException(
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

        try {
            $session->port = $this->findUnusedPort();
        } catch (\RuntimeException) {
            throw new ContainerStartException('Capacity limited reached, try again later or adjust distributor configuration.');
        }

        $session->containerHost = $randomHost['host'];
        [$userName, $privateKey] = $randomHost['privateKey'];

        $key = PublicKeyLoader::load(
            file_get_contents('ssh/' . $privateKey),
            $randomHost['privateKey'][2] ?? false
        );
        $ssh = new SSH2($session->containerHost);
        if (!$ssh->login($userName, $key)) {
            $this->serviceContainer->logger->error('startContainer() failed to SSH into the containerHost');

            throw new ContainerStartException('Can\'t login to containerHost! Configuration issue?');
        }

        $containerStartCommand = sprintf(
            "docker run --rm --name %s -d -p %d:8080 %s",
            "wrp_session_$session->id",
            $session->port,
            self::DOCKER_IMAGE
        );

        if (!$ssh->exec(
            $containerStartCommand,
            function($shellOutput) use ($session)
            {
                if (!$this->isContainerIdValid($shellOutput)) {
                    $this->serviceContainer->logger->warning(
                        'Container start seems to have failed, unexpected output from Docker',
                        [
                            'shellOutput' => $shellOutput,
                            'sessionId' => $session->id,
                            'port' => $session->port,
                            'containerHost' => $session->containerHost,
                        ]
                    );

                    throw new ContainerStartException('Docker command returned unexpected output on container start! Output was: ' . $shellOutput);
                }

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
            }) // end ssh exec call
        ) { // start if body
            $this->serviceContainer->logger->info(
                'startContainer() failed to spin up new container',
                [
                    'containerId' => $session->wrpContainerId,
                ]
            );

            throw new ContainerStartException("Can't send command to start container on determined host! Temporary network issue maybe?");
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

        $key = PublicKeyLoader::load(
            file_get_contents('ssh/' . $privateKey),
            $this->privateKeys[$hostIndex][2] ?? false
        );
        $ssh = new SSH2($containerHost);
        if (!$ssh->login($userName, $key)) {
            throw new \RuntimeException('Can\'t login to containerHost! Configuration issue?');
        }

        $containerStopCommand = sprintf(
            "docker stop %s",
            $session->wrpContainerId,
        );

        if (!$ssh->exec($containerStopCommand, function($shellOutput) use ($session) {
            if (!$this->isContainerIdValid($shellOutput)) {
                $this->serviceContainer->logger->info(
                    'Container stop seems to have failed, unexpected output from Docker. Most likely the container already is gone.',
                    [
                        'shellOutput' => $shellOutput,
                        'sessionId' => $session->id,
                        'port' => $session->port,
                        'containerHost' => $session->containerHost,
                    ]
                );
            }

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

            if (($startPort + $maxContainers) <= $result['nextPort']) {
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


    /**
     * @throws HostConfigurationMismatchException
     */
    private function readConfiguredHosts(): array
    {
        $containerHosts = explode(',', $_ENV['CONTAINER_HOSTS']);
        $containerHostKeys = explode(',', $_ENV['CONTAINER_HOSTS_KEYS']);
        foreach ($containerHostKeys as $key => $containerHostKey) {
            $containerHostKeys[$key] = explode('~', $containerHostKey);
        }

        if (\count($containerHosts) !== \count($containerHostKeys)) {
            $this->serviceContainer->logger->error(
                'Count of privateKeys does not match count of containerHosts!',
                [
                    'containerHosts' => $containerHosts,
                    'privateKeys' => $containerHostKeys,
                ]
            );

            throw new HostConfigurationMismatchException('Count of privateKeys does not match count of containerHosts!');
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

    private function isContainerIdValid(string $dockerCommandOutput): bool
    {
        return (bool) preg_match(
            '/^([a-z0-9]{64})$/',
            $dockerCommandOutput
        );
    }
}