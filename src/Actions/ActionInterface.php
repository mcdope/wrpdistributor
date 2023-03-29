<?php

declare(strict_types = 1);

namespace AmiDev\WrpDistributor\Actions;

use AmiDev\WrpDistributor\ServiceContainer;
use AmiDev\WrpDistributor\Session;

/**
 * @psalm-api
 */
interface ActionInterface
{
    public function __construct(ServiceContainer $serviceContainer);

    public function __invoke(Session $session): void;
}
