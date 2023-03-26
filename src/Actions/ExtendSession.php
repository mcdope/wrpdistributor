<?php

namespace AmiDev\WrpDistributor\Actions;

use AmiDev\WrpDistributor\ServiceContainer;
use AmiDev\WrpDistributor\Session;

readonly class ExtendSession implements ActionInterface
{
    public function __construct(private ServiceContainer $serviceContainer)
    {
    }

    /**
     * Just upsert()s the session to update lastUsed, to prevent it from timing out
     */
    public function __invoke(Session $session): void
    {
        if ($session->wrpContainerId === null) {
            // @todo: implement HTTP 409, wait for alb
            // http_response_code(409);
            http_response_code(204);
            return; // no need to keep a session without container alive
        }

        try {
            $session->upsert();
            http_response_code(204);
        } catch (\Throwable $throwable) {
            http_response_code(503);

            $this->serviceContainer->logger->warning(
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
