<?php

namespace AmiDev\WrpDistributor\Actions;

use AmiDev\WrpDistributor\DockerManager;
use AmiDev\WrpDistributor\Exceptions\Docker\ContainerStartException;
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
        $clientWantsTLS = isset($_REQUEST['ssl']) && (bool)$_REQUEST['ssl'];

        try {
            $this->serviceContainer->dockerManager->startContainer($session, $clientWantsTLS);
            $session->upsert();

            header('Content-Type: text/xml');
            http_response_code(202);

            echo sprintf(
                '<xml><wrpUrl>%s:%d</wrpUrl><token>%s</token></xml>',
                (string) $session->containerHost,
                (int) $session->port,
                $session->authToken
            );

            exit(0);
        } catch (ContainerStartException $containerStartException) {
            if (DockerManager::EXCEPTION_ALREADY_HAS_CONTAINER === $containerStartException->getCode()) {
                $session->upsert();

                http_response_code(204);

                $this->serviceContainer->logger->debug(
                    'Container already running',
                    [
                        'containerHost' => $session->containerHost,
                        'containerId' => $session->wrpContainerId,
                        'port' => $session->port,
                    ]
                );

                exit(0);
            }

            http_response_code(503);

            echo sprintf(
                '<h1>%s</h1><p>%s</p>',
                'Docker container for this session could not be started!',
                $containerStartException->getMessage(),
            );

            exit(1);
        } catch (\Throwable $throwable) {
            http_response_code(503);

            $this->serviceContainer->logger->debug(
                sprintf('Unexpected throwable occurred in %s', self::class),
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
                'Unexpected problem while starting the container for your session.',
                $throwable->getMessage(),
                $throwable->getTraceAsString()
            );

            exit(1);
        }
    }
}
