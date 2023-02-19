<?php

namespace AmiDev\WrpDistributor;

class Statistics
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

        return [
            'activeSessions' => $sessionCount,
            'containersRunning' => $portsUsedCount,
            'remainingContainers' => $totalMaxContainers - $portsUsedCount,
            'containerHosts' => $containerHostsAvailable,
            'containerHostsWithSessions' => serialize($sessionsPerHost),
        ];
    }

    public function insert(array $dataPoint): void
    {
        $statement = $this->serviceContainer->pdo->prepare(
            'INSERT INTO `statistics` (
                         activeSessions, containersRunningTotal, remainingContainersTotal, 
                         containerHostsAvailable, containersInUsePerHost
                   ) VALUES (
                         :activeSessions, :containersRunning, :remainingContainers, :containerHosts, :containerHostsWithSessions
                   )'
        );

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
        ');

        $statement->execute(['from' => $from->format('c'), 'till' => $till->format('c')]);

        $returnValue = [];
        foreach ($statement->fetchAll(\PDO::FETCH_ASSOC) as $dataPoint) {
            $containersPerHost = unserialize($dataPoint['containersInUsePerHost'], []);

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
            SELECT timeOfCapture, activeSessions as \'Active sessions\', containersRunningTotal as \'Active containers\', remainingContainersTotal as \'Remaining containers\' 
            FROM `statistics` WHERE timeOfCapture >= :from AND timeOfCapture <= :till
        ');

        $statement->execute(['from' => $from->format('c'), 'till' => $till->format('c')]);
        return $statement->fetchAll();
    }

    public static function createStatisticsTableIfNotExisting(ServiceContainer $serviceContainer): void
    {
        $tableExists = $serviceContainer->pdo->query(
            "SELECT EXISTS (
                    SELECT TABLE_NAME FROM information_schema.TABLES 
                    WHERE TABLE_SCHEMA LIKE 'wrpdistributor' 
                      AND TABLE_TYPE LIKE 'BASE TABLE' 
                      AND TABLE_NAME = 'statistics'
               )"
        )->fetch();

        if (0 === (int)$tableExists[0]) {
            $serviceContainer->pdo->query(file_get_contents('db/statistics.sql'));
            $serviceContainer->logger->info(
                'Statistics table not found, created'
            );
        }
    }
}