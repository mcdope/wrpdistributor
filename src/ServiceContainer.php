<?php

namespace AmiDev\WrpDistributor;

class ServiceContainer
{
    public function __construct(
        public Logger $logger,
        public \PDO $pdo,
        public ?DockerManager $dockerManager = null,
    ) {
        $this->dockerManager = new DockerManager($this);
    }
}