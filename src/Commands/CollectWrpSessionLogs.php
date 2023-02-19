<?php

declare(strict_types=1);

namespace AmiDev\WrpDistributor\Commands;

use AmiDev\WrpDistributor\Session;
use Symfony\Component\Console\Exception\InvalidArgumentException;
use Symfony\Component\Console\Helper\ProgressBar;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

final class CollectWrpSessionLogs extends Command
{
    /**
     * @noinspection PhpUnused
     *
     * @throws InvalidArgumentException
     */
    protected function configure(): void
    {
        $this->setName('containers:log:collect');
        $this->setDescription('Collect wrp.log from running containers');
    }

    /**
     * @noinspection PhpUnused
     *
     * @throws InvalidArgumentException
     */
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $sessionsWithContainer = $this->getSessionsWithContainer();
        if (false === $sessionsWithContainer || 0 === count($sessionsWithContainer)) {
            return self::SUCCESS;
        }

        $progressBar = new ProgressBar($output, count($sessionsWithContainer));
        $success = $error = 0;

        foreach ($sessionsWithContainer as $sessionToLoad) {
            try {
                $session = Session::loadFromDatabaseById($this->serviceContainer, (int) $sessionToLoad['id']);
                $this->serviceContainer->dockerManager->getWrpLog($session);

                $success++;
            } catch (\Throwable $throwable) {
                $output->writeln('WARN: \Throwable was thrown, message was: ' . $throwable->getMessage());

                $this->serviceContainer->logger->warning(
                    'Throwable while getting wrp log! Message: ' . $throwable->getMessage(),
                    $throwable->getTrace()
                );

                $error++;
            }

            /** @noinspection DisconnectedForeachInstructionInspection */
            $progressBar->advance();
        }
        $progressBar->finish();
        $output->writeln('');
        $output->writeln(
            sprintf(
                'INFO: Got logs of %d containers, for %d containers it failed.',
                $success,
                $error
            )
        );

        $this->serviceContainer->logger->info(
            sprintf(
                'Got logs of %d containers, for %d containers it failed. Check entries above for details.',
                $success,
                $error
            )
        );

        return self::SUCCESS;
    }

    public function getSessionsWithContainer(): false|array
    {
        $containersStmt = $this->serviceContainer->pdo->query(
            'SELECT id FROM sessions
                   WHERE 1=1
                     AND `containerHost` IS NOT NULL
                     AND `port` IS NOT NULL
                     AND `wrpContainerId` IS NOT NULL'
        );
        return $containersStmt->fetchAll(0);
    }
}
