<?php

declare(strict_types = 1);

namespace AmiDev\WrpDistributor\Actions;

use AmiDev\WrpDistributor\DockerManager;
use AmiDev\WrpDistributor\Exceptions\Docker\ContainerStartException;
use AmiDev\WrpDistributor\ServiceContainer;
use AmiDev\WrpDistributor\Session;

final readonly class StartSession implements ActionInterface
{
    public function __construct(private ServiceContainer $serviceContainer)
    {
    }

    /**
     * Starts container for this session and upsert()s it if successfully started.
     *
     * @throws \Exception
     */
    public function __invoke(Session $session): void
    {
        parse_str(file_get_contents('php://input'), $input);
        $clientWantsTLS = isset($input['ssl']) && (bool) $input['ssl'];

        try {
            $this->serviceContainer->dockerManager->startContainer($session, $clientWantsTLS);

            header('Content-Type: text/xml');
            http_response_code(202);

            echo sprintf(
                '<xml><wrpUrl>%s:%d</wrpUrl><token>%s</token></xml>',
                (string) $session->containerHost,
                (int) $session->port,
                (string) $session->authToken,
            );

            return;
        } catch (ContainerStartException $containerStartException) {
            if (DockerManager::EXCEPTION_ALREADY_HAS_CONTAINER === $containerStartException->getCode()) {
                http_response_code(204);

                return;
            }

            http_response_code(503);
            echo sprintf(
                '<h1>%s</h1><p>%s</p>',
                'Docker container for this session could not be started!',
                $containerStartException->getMessage(),
            );

            exit(1);
        } catch (\PDOException $pdoException) {
            $this->serviceContainer->logger->warning(
                sprintf('PDOException occurred in %s, shutting down started container again', self::class),
                [
                    'message' => $pdoException->getMessage(),
                    'trace' => $pdoException->getTrace(),
                ],
            );

            $this->serviceContainer->dockerManager->stopContainer($session);

            http_response_code(503);
            echo sprintf(
                '<h1>%s</h1><p>%s</p>',
                'Session persisting failed!',
                $pdoException->getMessage(),
            );

            exit(1);
        } catch (\Throwable $throwable) {
            http_response_code(503);

            $this->serviceContainer->logger->warning(
                sprintf('Unexpected throwable occurred in %s', self::class),
                [
                    'message' => $throwable->getMessage(),
                    'trace' => $throwable->getTrace(),
                ],
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
                $throwable->getTraceAsString(),
            );

            exit(1);
        }
    }
}
