<?php

declare(strict_types=1);

namespace AmiDev\WrpDistributor\Commands;

use AmiDev\WrpDistributor\DockerManager;
use AmiDev\WrpDistributor\Session;
use Symfony\Component\Console\Exception\InvalidArgumentException;
use Symfony\Component\Console\Helper\ProgressBar;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

final class CleanUnusedSessions extends Command
{
    /**
     * @param mixed $timeout
     * @return array|false
     */
    private function getUnusedSessions(int $timeout): array|false
    {
        $unusedSessionsStmt = $this->serviceContainer->pdo->prepare(
            ' SELECT id, wrpContainerId, containerHost, port
                    FROM sessions WHERE lastUsed <= :timeout'
        );

        $datetime = new \DateTime("now -{$timeout} minutes");
        $unusedSessionsStmt->execute(['timeout' => $datetime->format('c')]);
        return $unusedSessionsStmt->fetchAll(0);
    }

    /**
     * @noinspection PhpUnused
     *
     * @throws InvalidArgumentException
     */
    protected function configure(): void
    {
        $this->setName('cleanup:sessions');
        $this->setDescription('Cleans unused sessions');
        $this->addArgument(
            'timeout',
            InputArgument::OPTIONAL,
            'Minutes to have passed to consider a session being unused',
            10
        );
    }

    /**
     * @noinspection PhpUnused
     *
     * @throws InvalidArgumentException
     */
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $timeout = (int) $input->getArgument('timeout');
        $unusedSessions = $this->getUnusedSessions($timeout);
        if (false === $unusedSessions || 0 === count($unusedSessions)) {
            return self::SUCCESS;
        }

        $progressBar = new ProgressBar($output, count($unusedSessions));
        $success = $error = 0;

        foreach ($unusedSessions as $sessionToCleanup) {
            try {
                $session = Session::loadFromDatabaseById($this->serviceContainer, (int) $sessionToCleanup['id']);
                if (!empty($sessionToCleanup['port']) &&
                    !empty($sessionToCleanup['containerHost']) &&
                    !empty($sessionToCleanup['wrpContainerId'])
                ) {
                    $this->serviceContainer->dockerManager->stopContainer($session);
                }

                $session->delete();
                $success++;
            } catch (\LogicException $logicException) {
                if (DockerManager::EXCEPTION_HAS_NO_CONTAINER === $logicException->getCode()) {
                    // Not an error, nothing to clean up
                    $success++;
                } else {
                    throw $logicException;
                }
            } catch (\Throwable $throwable) {
                $output->writeln('WARN: \Throwable was thrown, message was: ' . $throwable->getMessage());

                $this->serviceContainer->logger->warning(
                    'Throwable while cleaning up unused sessions! Message: ' . $throwable->getMessage(),
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
                'INFO: Cleanup of %d sessions finished, %d sessions terminated successfully - %d sessions failed to terminate.',
                count($unusedSessions),
                $success,
                $error
            )
        );

        $this->serviceContainer->logger->info(
            sprintf(
                'Cleanup of %d sessions finished, %d sessions terminated successfully - %d sessions failed to terminate. Check entries above for details.',
                count($unusedSessions),
                $success,
                $error
            )
        );

        return self::SUCCESS;
    }
}
