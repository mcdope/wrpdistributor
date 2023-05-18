<?php

declare(strict_types = 1);

namespace AmiDev\WrpDistributor;

final class Statistics
{
    public function __construct(private ServiceContainer $serviceContainer)
    {
    }

    public function collectDatapoint(): array
    {
        $sessionCount = $this->serviceContainer->pdo->query('SELECT COUNT(`id`) FROM `sessions`')->fetch()[0];
        $portsUsedCount = $this->serviceContainer->dockerManager->countsPortsUsed();
        $containerHostsAvailable = $this->serviceContainer->dockerManager->countAvailableContainerHosts();
        $sessionsPerHost = $this->serviceContainer->dockerManager->countSessionsPerContainerHost();
        $totalMaxContainers = $this->serviceContainer->dockerManager->countTotalMaxContainers();
        $uniqueClientIps = $this->serviceContainer->statistics->getUniqueClientIps();

        return [
            'activeSessions' => $sessionCount,
            'containersRunning' => $portsUsedCount,
            'remainingContainers' => $totalMaxContainers - $portsUsedCount,
            'containerHosts' => $containerHostsAvailable,
            'containerHostsWithSessions' => serialize($sessionsPerHost),
            'uniqueClientIps' => $uniqueClientIps,
        ];
    }

    public function insert(array $dataPoint): void
    {
        $statement = $this->serviceContainer->pdo->prepare('
            INSERT INTO `statistics` (
                 activeSessions, containersRunningTotal, remainingContainersTotal,
                 containerHostsAvailable, containersInUsePerHost, uniqueClientIps
           ) VALUES (
                 :activeSessions, :containersRunning, :remainingContainers, :containerHosts, :containerHostsWithSessions, :uniqueClientIps
           )
        ');

        $statement->execute($dataPoint);
    }

    public function getContainerHostUsageForTimeframe(?\DateTime $from = null, ?\DateTime $till = null): array
    {
        if (null === $from) {
            $from = new \DateTime('-30 days');
        }

        if (null === $till) {
            $till = new \DateTime('now');
        }

        $statement = $this->serviceContainer->pdo->prepare('
            SELECT timeOfCapture, containersInUsePerHost 
            FROM `statistics` WHERE timeOfCapture >= :from AND timeOfCapture <= :till
            ORDER BY timeOfCapture ASC
        ');

        $statement->execute(['from' => $from->format('c'), 'till' => $till->format('c')]);

        $returnValue = [];
        foreach ($statement->fetchAll(\PDO::FETCH_ASSOC) as $dataPoint) {
            $containersPerHost = @unserialize($dataPoint['containersInUsePerHost'], []);
            if (!is_array($containersPerHost)) {
                continue;
            }

            foreach ($containersPerHost as $host => $containerCount) {
                $returnValue[$dataPoint['timeOfCapture']][] = [$host => $containerCount];
            }
        }

        return $returnValue;
    }

    public function getTotalsForTimeframe(?\DateTime $from = null, ?\DateTime $till = null): array
    {
        if (null === $from) {
            $from = new \DateTime('-30 days');
        }

        if (null === $till) {
            $till = new \DateTime('now');
        }

        $statement = $this->serviceContainer->pdo->prepare('
            SELECT timeOfCapture, activeSessions as \'Active sessions\', containersRunningTotal as \'Active containers\', uniqueClientIps as \'Active unique clients\' 
            FROM `statistics` WHERE timeOfCapture >= :from AND timeOfCapture <= :till
            ORDER BY timeOfCapture ASC
        ');

        $statement->execute(['from' => $from->format('c'), 'till' => $till->format('c')]);

        return $statement->fetchAll();
    }

    public function getTotalSessionsServed(): int
    {
        $statement = $this->serviceContainer->pdo->prepare("
            SELECT (AUTO_INCREMENT - 1) FROM information_schema.tables 
            WHERE table_name = 'sessions' 
              AND table_schema = DATABASE()
        ");

        $statement->execute([]);

        return (int) $statement->fetchColumn(0);
    }
    public function getMaxConcurrentContainersServed(): int
    {
        $statement = $this->serviceContainer->pdo->prepare("SELECT MAX(containersRunningTotal) FROM statistics");
        $statement->execute([]);

        return (int) $statement->fetchColumn(0);
    }

    public function getUniqueClientIps(): int
    {
        $statement = $this->serviceContainer->pdo->prepare("SELECT COUNT(DISTINCT clientIp) FROM sessions");
        $statement->execute([]);

        return (int) $statement->fetchColumn(0);
    }
}
