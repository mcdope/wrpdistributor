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
     * Displays a small status information pages
     */
    public function __invoke(Session $session): void
    {
        try {
            $sessionCount = $session->countAllSessions();
            $portsUsedCount = $this->serviceContainer->dockerManager->countsPortsUsed();
            $containerHostsAvailable = $this->serviceContainer->dockerManager->countAvailableContainerHosts();
            $sessionsPerHost = $this->serviceContainer->dockerManager->countSessionsPerContainerHost();
            $totalMaxContainers = $this->serviceContainer->dockerManager->countTotalMaxContainers();

            echo '<h1>wrp-distributor status</h1>';
            echo '<p>It\'s alive! Here are some statistics about the current instance:</p>';
            echo '<ul>';
            echo sprintf("<li>Current session count: %s</li>", $sessionCount);
            echo sprintf("<li>Current container count: %s</li>", $portsUsedCount);
            echo sprintf("<li>Available container hosts: %s</li>", $containerHostsAvailable);
            if (\count($sessionsPerHost)) {
                echo '<li>Container hosts in use:<br><ol>';
                foreach ($sessionsPerHost as $containerHost => $sessionCountCurrentHost) {
                    echo sprintf(
                        "<li>%s: %d sessions running (allowed: %d)</li>",
                        substr(md5($containerHost), 0, 8),
                        $sessionCountCurrentHost,
                        $this->serviceContainer->dockerManager->getMaxContainersForHost($containerHost)
                    );
                }
                echo '</ol></li>';
            }
            echo sprintf(
                "<li>Unused configuration potential / remaining containers: %s</li>",
                $totalMaxContainers - $portsUsedCount
            );
            echo '</ul>';

            http_response_code(200);
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
