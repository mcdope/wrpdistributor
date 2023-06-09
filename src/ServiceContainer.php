<?php

declare(strict_types = 1);

namespace AmiDev\WrpDistributor;

use AmiDev\WrpDistributor\Exceptions\Docker\HostConfigurationMismatchException;

final class ServiceContainer
{
    /** @psalm-readonly */
    public DockerManager $dockerManager;

    /** @psalm-readonly */
    public Statistics $statistics;

    /**
     * @throws HostConfigurationMismatchException
     */
    public function __construct(
        public Logger $logger,
        public \PDO $pdo,
    ) {
        $this->dockerManager = new DockerManager($this);
        $this->statistics = new Statistics($this);
    }
}
