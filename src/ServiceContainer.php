<?php

namespace AmiDev\WrpDistributor;

class ServiceContainer
{
    public function __construct(
        public Logger $logger,
        public \PDO $pdo,
    ) {}
}