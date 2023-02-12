<?php

namespace AmiDev\WrpDistributor\Actions;

use AmiDev\WrpDistributor\ServiceContainer;
use AmiDev\WrpDistributor\Session;

readonly class DeadOrAlive implements ActionInterface
{
    public function __construct(private ServiceContainer $serviceContainer)
    {
    }

    /**
     * Just upsert()s the session to update lastUsed, to prevent it from timing out
     */
    public function __invoke(Session $session): void
    {
        try {
            $sessionCount = $this->serviceContainer->pdo->query('SELECT COUNT(`id`) FROM `sessions`')->fetch();
            $portsUsedCount = $this->serviceContainer->pdo->query('SELECT COUNT(`port`) FROM `sessions`')->fetch();

            echo '<h1>wrp-distributor status</h1>';
            echo '<p>It\'s alive! Here are some statistics about the current instance:</p>';
            echo '<ul>';
            echo '<li>Current session count: ' . $sessionCount[0] . '</li>';
            echo '<li>Current container count: ' . $portsUsedCount[0] . '</li>';
            echo '<li>Unused configuration potential / remaining containers: ' . ($_ENV['MAX_CONTAINERS_RUNNING'] - $portsUsedCount[0]) . '</li>';
            echo '</ul>';

            http_response_code(200);
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