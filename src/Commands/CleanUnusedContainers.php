<?php

declare(strict_types = 1);

namespace AmiDev\WrpDistributor\Commands;

use AmiDev\WrpDistributor\Session;
use Symfony\Component\Console\Exception\InvalidArgumentException;
use Symfony\Component\Console\Helper\ProgressBar;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

final class CleanUnusedContainers extends Command
{
    /**
     * @noinspection PhpUnused
     *
     * @throws InvalidArgumentException
     */
    protected function configure(): void
    {
        $this->setName('cleanup:containers');
        $this->setDescription('Cleans unused containers (YOU SHOULD CLEAN UP SESSIONS FIRST!)');
    }

    /**
     * @noinspection PhpUnused
     *
     * @throws InvalidArgumentException|\Exception
     */
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $success = $error = $skipped = 0;
        $containersRunningCurrently = $this->serviceContainer->dockerManager->getCurrentlyRunningContainersByHosts();

        if (empty($containersRunningCurrently)) {
            return self::SUCCESS;
        }

        $hostProgressBar = new ProgressBar($output, count($containersRunningCurrently));
        foreach ($containersRunningCurrently as $hostname => $containersRunning) {
            $containerProgressBar = new ProgressBar($output, count($containersRunning));
            foreach ($containersRunning as $sessionIdFromContainerName) {
                try {
                    $session = Session::loadFromDatabaseById($this->serviceContainer, $sessionIdFromContainerName);

                    if (is_numeric($session->id) && !empty($session->wrpContainerId)) {
                        ++$skipped;
                        continue;
                    }
                } catch (\PDOException|\LogicException $exception) { // session not found, means we can shut down the container
                    try {
                        $output->writeln('Stopping container');
                        $this->serviceContainer->dockerManager->stopContainerBySessionIdAndHost(
                            $sessionIdFromContainerName,
                            $hostname,
                        );

                        ++$success;
                    } catch (\Throwable $throwable) {
                        $output->writeln('WARN: \Throwable was thrown, message was: ' . $throwable->getMessage());

                        $this->serviceContainer->logger->warning(
                            'Throwable while cleaning up unused containers! Message: ' . $throwable->getMessage(),
                            $throwable->getTrace(),
                        );

                        ++$error;
                    }
                }

                /** @noinspection DisconnectedForeachInstructionInspection */
                $containerProgressBar->advance();
            }
            $containerProgressBar->finish();

            /** @noinspection DisconnectedForeachInstructionInspection */
            $hostProgressBar->advance();
        }

        $hostProgressBar->finish();

        $output->writeln('');
        $output->writeln(
            sprintf(
                'INFO: Cleanup of %d containers finished, %d containers terminated successfully - %d containers failed to terminate - %d containers skipped (session exists).',
                array_sum([$success, $error, $skipped]),
                $success,
                $error,
                $skipped,
            ),
        );

        $this->serviceContainer->logger->info(
            sprintf(
                'INFO: Cleanup of %d containers finished, %d containers terminated successfully - %d containers failed to terminate - %d containers skipped (session exists).',
                array_sum([$success, $error, $skipped]),
                $success,
                $error,
                $skipped,
            ),
        );

        return self::SUCCESS;
    }
}
