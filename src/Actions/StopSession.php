<?php

declare(strict_types = 1);

namespace AmiDev\WrpDistributor\Actions;

use AmiDev\WrpDistributor\DockerManager;
use AmiDev\WrpDistributor\ServiceContainer;
use AmiDev\WrpDistributor\Session;

final readonly class StopSession implements ActionInterface
{
    public function __construct(private ServiceContainer $serviceContainer)
    {
    }

    public function __invoke(Session $session): void
    {
        try {
            $this->serviceContainer->dockerManager->stopContainer($session);
            http_response_code(202);
        } catch (\LogicException $logicException) {
            if (DockerManager::EXCEPTION_HAS_NO_CONTAINER === $logicException->getCode()) {
                http_response_code(204);
            }
        } catch (\Throwable $throwable) {
            http_response_code(503);

            $this->serviceContainer->logger->warning(
                sprintf('Throwable occurred in %s', self::class),
                [
                    'message' => $throwable->getMessage(),
                    'trace' => $throwable->getTrace(),
                ],
            );

            exit(1);
        }
    }
}
