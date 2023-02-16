<?php

namespace AmiDev\WrpDistributor;

use AmiDev\WrpDistributor\Exceptions\Docker\HostConfigurationMismatchException;

class ServiceContainer
{
    /**
     * @throws HostConfigurationMismatchException
     */
    public function __construct(
        public Logger $logger,
        public \PDO $pdo,
        public ?DockerManager $dockerManager = null,
    ) {
        $this->dockerManager = new DockerManager($this);
    }
}