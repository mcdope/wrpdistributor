<?php

namespace AmiDev\WrpDistributor\Actions;

use AmiDev\WrpDistributor\ServiceContainer;
use AmiDev\WrpDistributor\Session;

readonly class StopSession implements ActionInterface
{
    public function __construct(private ServiceContainer $serviceContainer)
    {
    }

    public function __invoke(Session $session): void
    {
        try {
            $session->stopContainer();
            $session->upsert();

            http_response_code(202);
        } catch (\Throwable $throwable) {
            http_response_code(503);

            $this->serviceContainer->logger->debug(
                sprintf('Throwable occurred in %s', self::class),
                [
                    'message' => $throwable->getMessage(),
                    'trace' => $throwable->getTrace(),
                ]
            );

            exit(1);
        }
    }
}