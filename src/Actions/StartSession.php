<?php

namespace AmiDev\WrpDistributor\Actions;

use AmiDev\WrpDistributor\ServiceContainer;
use AmiDev\WrpDistributor\Session;

readonly class StartSession implements ActionInterface
{
    public function __construct(private ServiceContainer $serviceContainer)
    {
    }

    /**
     * Starts container for this session and upsert()s it if successfully started
     *
     * @throws \Exception
     */
    public function __invoke(Session $session): void
    {
        try {
            $session->startContainer();
            $session->upsert();

            header('Content-Type: text/xml');
            http_response_code(202);

            echo sprintf(
                '<xml><wrpUrl>%s:%d</wrpUrl></xml>',
                $session->containerHost,
                $session->port
            );
        } catch (\Throwable $throwable) {
            http_response_code(503);

            $this->serviceContainer->logger->debug(
                sprintf('Throwable occurred in %s', self::class),
                [
                    'message' => $throwable->getMessage(),
                    'trace' => $throwable->getTrace(),
                ]
            );

            echo sprintf(
                '
                            <h1>%s</h1>
                            <p>%s</p>
                            <p>
                                <pre>%s</pre>
                            </p>',
                'Docker container for this session could not be started! Most likely a temporary resource issue...',
                $throwable->getMessage(),
                $throwable->getTraceAsString()
            );

            exit(1);
        }
    }
}