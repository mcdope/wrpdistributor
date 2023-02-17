<?php

namespace AmiDev\WrpDistributor;

use AmiDev\WrpDistributor\Exceptions\Docker\ContainerStartException;
use AmiDev\WrpDistributor\Exceptions\Docker\HostConfigurationMismatchException;
use AmiDev\WrpDistributor\Exceptions\Docker\InvalidBalanceStrategyException;
use AmiDev\WrpDistributor\Exceptions\Docker\LoadBalancingException;
use AmiDev\WrpDistributor\Exceptions\Docker\LoadBalancingFailedException;
use phpseclib3\Crypt\PublicKeyLoader;
use phpseclib3\Net\SSH2;

class DockerManager
{
    private const DOCKER_IMAGE = 'alb42/amifoxserver:latest';
    private const BALANCE_STRATEGY_EQUAL = 'equal';
    private const BALANCE_STRATEGY_FILLHOST = 'fillhost';
    public const EXCEPTION_ALREADY_HAS_CONTAINER = 128;
    public const EXCEPTION_HAS_NO_CONTAINER = 256;


    /**
     * @throws HostConfigurationMismatchException
     */
    public function __construct(
        private readonly ServiceContainer $serviceContainer,
        private array                     $containerHosts = [],
        private array                     $privateKeys = [],
        private array                     $maxContainers = [],
    ) {
        [$this->containerHosts, $this->privateKeys, $this->maxContainers] = $this->readConfiguredHosts();
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

    public function countTotalMaxContainers(): int
    {
        return (int) array_sum($this->maxContainers);
    }

    public function getMaxContainersForHost(string $containerHost): int
    {
        return $this->maxContainers[
            array_search($containerHost, $this->containerHosts, true)
        ];
    }

    /**
     * @throws ContainerStartException
     * @throws \RuntimeException
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
                    (string) $session->containerHost
                ),
                self::EXCEPTION_ALREADY_HAS_CONTAINER
            );
        }

        try {
            $determinedContainerHost = $this->getHostByBalanceStrategy();
        } catch (LoadBalancingException) {
            throw new ContainerStartException('Could not find an available containerHost, try again later or adjust distributor configuration.');
        }

        $this->serviceContainer->logger->debug(
            'startContainer() determined new containerHost',
            [
                'host' => $determinedContainerHost['host'],
                'auth' => $determinedContainerHost['privateKey'],
                'maxContainers' => $determinedContainerHost['maxContainers'],
            ]
        );

        try {
            $session->port = $this->findUnusedPort($determinedContainerHost['host']);
        } catch (\RuntimeException) {
            throw new ContainerStartException('Capacity limited reached, try again later or adjust distributor configuration.');
        }

        $session->containerHost = $determinedContainerHost['host'];
        [$userName, $privateKey] = $determinedContainerHost['privateKey'];

        if (isset($determinedContainerHost['privateKey'][2])) {
            $key = PublicKeyLoader::load(
                file_get_contents('ssh/' . $privateKey),
                $determinedContainerHost['privateKey'][2]
            );
        } else {
            $key = PublicKeyLoader::load(file_get_contents('ssh/' . $privateKey));
        }
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
            function(string $shellOutput) use ($session)
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
            $this->serviceContainer->logger->warning(
                'startContainer() failed to spin up new container',
                [
                    'containerId' => $session->wrpContainerId,
                ]
            );

            throw new ContainerStartException("Can't send command to start container on determined host! Temporary network issue maybe?");
        }
    }

    /**
     * @throws \LogicException
     * @throws \RuntimeException
     */
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

        if (isset($this->privateKeys[$hostIndex][2])) {
            $key = PublicKeyLoader::load(
                file_get_contents('ssh/' . $privateKey),
                $this->privateKeys[$hostIndex][2]
            );
        } else {
            $key = PublicKeyLoader::load(file_get_contents('ssh/' . $privateKey));
        }
        $ssh = new SSH2($containerHost);
        if (!$ssh->login($userName, $key)) {
            throw new \RuntimeException('Can\'t login to containerHost! Configuration issue?');
        }

        $containerStopCommand = sprintf(
            "docker stop %s",
            $session->wrpContainerId,
        );

        if (!$ssh->exec($containerStopCommand, function(string $shellOutput) use ($session) {
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
            'privateKey' => $this->privateKeys[$randomHostIndex],
            'maxContainers' => $this->maxContainers[$randomHostIndex],
        ];
    }

    /**
     * @throws InvalidBalanceStrategyException
     * @throws LoadBalancingFailedException
     */
    private function getHostByBalanceStrategy(): array
    {
        $balanceStrategy = $_ENV['CONTAINER_DISTRIBUTION_METHOD'];
        $containerHostsWithSessionCount = $this->countSessionsPerContainerHost();
        $sessionCountByContainerHost = [];
        foreach ($containerHostsWithSessionCount as $containerHostWithSessionCount) {
            $sessionCountByContainerHost[$containerHostWithSessionCount['containerHost']] = $containerHostWithSessionCount['count'];
        }

        if (0 === \count($sessionCountByContainerHost)) {
            $this->serviceContainer->logger->debug('Load balancing can\'t balance anything, no containers running yet. Passing to getRandomHost().');

            return $this->getRandomHost();
        }

        if (self::BALANCE_STRATEGY_EQUAL === $balanceStrategy) {
            return $this->getHostByEqualStrategy($sessionCountByContainerHost);
        }

        if (self::BALANCE_STRATEGY_FILLHOST === $balanceStrategy) {
            return $this->getHostByFillhostStrategy($sessionCountByContainerHost);
        }

        $this->serviceContainer->logger->error(
            'The configured balance strategy is invalid!',
            ['balanceStrategyFromEnv' => $balanceStrategy]
        );

        throw new InvalidBalanceStrategyException('The configured balance strategy is invalid!');
    }

    /**
     * @todo: actually this gets the next port, not the first unused. should also use lower ports then max-1 if still larger then start port
     * @noinspection PhpUnusedParameterInspection
     * @psalm-suppress UnusedParam
     * @throws \RuntimeException
     */
    private function findUnusedPort(string $containerHost): int
    {
        /* // it should be this query, but since we use different hostnames for the same host currently for testing that's not possible yet
        $query = sprintf(
            "SELECT MAX(`port`)+1 as 'nextPort' FROM `sessions` WHERE containerHost = '%s'",
            $containerHost
        );
        */
        $query = "SELECT MAX(`port`)+1 as 'nextPort' FROM `sessions`";

        if ($result = $this->serviceContainer->pdo->query($query)->fetch()) {
            if (empty($result['nextPort'])) {
                $this->serviceContainer->logger->debug(
                    'findUnusedPort() falling back to START_PORT because of empty result',
                    [
                        'nextPort' => (int) $_ENV['START_PORT'],
                    ]
                );

                return (int) $_ENV['START_PORT'];
            }

            if (65535 <= $result['nextPort']) {
                throw new \RuntimeException('No free ports left!');
            }

            $this->serviceContainer->logger->debug(
                'findUnusedPort() determined next port to use',
                [
                    'nextPort' => (int) $result['nextPort'],
                ]
            );

            return (int) $result['nextPort'];
        }

        $this->serviceContainer->logger->debug(
            'findUnusedPort() falling back to START_PORT at end of method',
            [
                'nextPort' => (int) $_ENV['START_PORT'],
            ]
        );

        return (int) $_ENV['START_PORT'];
    }


    /**
     * @throws HostConfigurationMismatchException
     */
    private function readConfiguredHosts(): array
    {
        $containerHosts = explode(',', $_ENV['CONTAINER_HOSTS']);
        $maxContainers = explode(',', $_ENV['MAX_CONTAINERS_RUNNING']);
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

        /*
        $this->serviceContainer->logger->debug(
            "readConfiguredHosts()",
            [
                'hosts' => $containerHosts,
                'keys' => $containerHostKeys,
                'maxContainers' => $maxContainers,
                'env' => $_ENV,
            ]
        );
        */

        return [
            $containerHosts,
            $containerHostKeys,
            $maxContainers
        ];
    }

    private function isContainerIdValid(string $dockerCommandOutput): bool
    {
        return (bool) preg_match(
            '/^([a-z0-9]{64})$/',
            $dockerCommandOutput
        );
    }

    /**
     * @throws LoadBalancingFailedException
     */
    public function getHostByEqualStrategy(array $sessionCountByContainerHost): array
    {
        arsort($sessionCountByContainerHost);
        if (\count($sessionCountByContainerHost) < \count($this->containerHosts)) {
            // not all hosts have at least one container yet. return next unused host
            $unusedContainerHosts = array_diff(
                $this->containerHosts,
                array_keys($sessionCountByContainerHost)
            );

            $this->serviceContainer->logger->debug(
                'Unused container hosts',
                [
                    'unusedHosts' => $unusedContainerHosts,
                    'usedHosts' => array_keys($sessionCountByContainerHost),
                ]
            );

            $keyOfFirstUnusedHost = array_key_first($unusedContainerHosts);
            if (null === $keyOfFirstUnusedHost) {
                throw new LoadBalancingFailedException('Couldn\t find $unusedContainerHosts key for first unused host!');
            }

            $indexOfFirstUnusedContainerHost = array_search(
                $unusedContainerHosts[$keyOfFirstUnusedHost],
                $this->containerHosts,
                true
            );

            $this->serviceContainer->logger->debug(
                'Equal load balancing selected new unused containerHost',
                [
                    'nextContainerHostByStrategy' => $this->containerHosts[$indexOfFirstUnusedContainerHost],
                    'maxContainersForHost' => $this->maxContainers[$indexOfFirstUnusedContainerHost],
                ]
            );

            return [
                'host' => $this->containerHosts[$indexOfFirstUnusedContainerHost],
                'privateKey' => $this->privateKeys[$indexOfFirstUnusedContainerHost],
                'maxContainers' => $this->maxContainers[$indexOfFirstUnusedContainerHost],
            ];
        }

        $hasAlreadyReachedEqualDistribution = \count(array_unique($sessionCountByContainerHost)) === 1;
        if ($hasAlreadyReachedEqualDistribution) {
            $this->serviceContainer->logger->debug('Load balancing can\'t balance anything, all hosts run the same amount of containers. Passing to getRandomHost().');

            return $this->getRandomHost();
        }

        $previousSessionCount = null;
        foreach ($sessionCountByContainerHost as $containerHost => $sessionCount) {
            $indexOfSelectedHost = array_search($containerHost, $this->containerHosts, true);
            if ($sessionCount >= $this->maxContainers[$indexOfSelectedHost]) {
                $this->serviceContainer->logger->warning(
                    'Equal load balancing configured, but not all containerHosts allow the same amount of maxContainers!',
                    [
                        'nextContainerHostByStrategy' => $this->containerHosts[$indexOfSelectedHost],
                        'currentSessionsOnHost' => $sessionCount,
                        'maxContainersForHost' => $this->maxContainers[$indexOfSelectedHost],
                    ]
                );

                continue;
            }

            if ($sessionCount < $previousSessionCount) {
                $this->serviceContainer->logger->debug(
                    'Equal load balancing selected new containerHost',
                    [
                        'nextContainerHostByStrategy' => $this->containerHosts[$indexOfSelectedHost],
                        'currentSessionsOnHost' => $sessionCount,
                        'maxContainersForHost' => $this->maxContainers[$indexOfSelectedHost],
                    ]
                );

                return [
                    'host' => $this->containerHosts[$indexOfSelectedHost],
                    'privateKey' => $this->privateKeys[$indexOfSelectedHost],
                    'maxContainers' => $this->maxContainers[$indexOfSelectedHost],
                ];
            }

            $previousSessionCount = $sessionCount;
        }

        $this->serviceContainer->logger->error(
            'Equal load balancing failed to find free containerHost, all hosts fully loaded!',
            [
                'sessionCountByContainerHost' => $sessionCountByContainerHost,
                'maxContainers' => $this->maxContainers,
            ]
        );

        throw new LoadBalancingFailedException('Equal load balancing failed to find containerHost!');
    }

    /**
     * @throws LoadBalancingFailedException
     */
    public function getHostByFillhostStrategy(array $sessionCountByContainerHost): array
    {
        asort($sessionCountByContainerHost);

        foreach ($sessionCountByContainerHost as $containerHost => $sessionCount) {
            $indexOfSelectedHost = array_search($containerHost, $this->containerHosts, true);

            if ($sessionCount < $this->maxContainers[$indexOfSelectedHost]) {
                $this->serviceContainer->logger->debug(
                    'Fillhost load balancing selected free containerHost',
                    [
                        'nextContainerHostByStrategy' => $containerHost,
                        'currentSessionsOnHost' => $sessionCount,
                        'maxContainersForHost' => $this->maxContainers[$indexOfSelectedHost],
                    ]
                );

                return [
                    'host' => $this->containerHosts[$indexOfSelectedHost],
                    'privateKey' => $this->privateKeys[$indexOfSelectedHost],
                    'maxContainers' => $this->maxContainers[$indexOfSelectedHost],
                ];
            }
        }

        if (\count($sessionCountByContainerHost) < \count($this->containerHosts)) {
            // not all hosts have at least one container yet. return next unused host
            $unusedContainerHosts = array_diff(
                $this->containerHosts,
                array_keys($sessionCountByContainerHost)
            );

            $this->serviceContainer->logger->debug(
                'Unused container hosts',
                [
                    'unusedHosts' => $unusedContainerHosts,
                    'usedHosts' => array_keys($sessionCountByContainerHost),
                ]
            );

            $keyOfFirstUnusedHost = array_key_first($unusedContainerHosts);
            if (null === $keyOfFirstUnusedHost) {
                throw new LoadBalancingFailedException('Couldn\t find $unusedContainerHosts key for first unused host!');
            }

            $indexOfFirstUnusedContainerHost = array_search(
                $unusedContainerHosts[$keyOfFirstUnusedHost],
                $this->containerHosts,
                true
            );

            $this->serviceContainer->logger->debug(
                'Fillhost load balancing selected new unused containerHost',
                [
                    'nextContainerHostByStrategy' => $this->containerHosts[$indexOfFirstUnusedContainerHost],
                    'maxContainersForHost' => $this->maxContainers[$indexOfFirstUnusedContainerHost],
                ]
            );

            return [
                'host' => $this->containerHosts[$indexOfFirstUnusedContainerHost],
                'privateKey' => $this->privateKeys[$indexOfFirstUnusedContainerHost],
                'maxContainers' => $this->maxContainers[$indexOfFirstUnusedContainerHost],
            ];
        }

        $this->serviceContainer->logger->error(
            'Fillhost load balancing failed to find free containerHost, all hosts fully loaded!',
            [
                'sessionCountByContainerHost' => $sessionCountByContainerHost,
                'maxContainers' => $this->maxContainers,
            ]
        );

        throw new LoadBalancingFailedException('Fillhost load balancing failed to find containerHost!');
    }
}