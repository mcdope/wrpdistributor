<?php

namespace AmiDev\WrpDistributor\Actions;

use AmiDev\WrpDistributor\ServiceContainer;
use AmiDev\WrpDistributor\Session;

interface ActionInterface {
    public function __construct(ServiceContainer $serviceContainer);
    public function __invoke(Session $session): void;
}