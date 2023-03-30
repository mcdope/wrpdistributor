<?php

declare(strict_types = 1);

/** @noinspection PhpMultipleClassDeclarationsInspection */

namespace AmiDev\WrpDistributor;

use Monolog\ErrorHandler;
use Monolog\Handler\RotatingFileHandler;
use Monolog\Logger as MonologLogger;
use Psr\Log\LoggerInterface;

class Logger
{
    private LoggerInterface $logger;

    public function __construct(?LoggerInterface $logger = null)
    {
        self::createLogDirectoryIfNotExists();
        $log = $logger ?? new MonologLogger('distributor');
        ErrorHandler::register($log);
        $log->pushHandler(new RotatingFileHandler('logs/distributor.log', 30));

        $this->logger = $log;
    }

    public function info(string|\Stringable $message, array $context = []): void
    {
        $this->logger->info($message, $context);
    }

    public function warning(string|\Stringable $message, array $context = []): void
    {
        $this->logger->warning($message, $context);
    }

    public function error(string|\Stringable $message, array $context = []): void
    {
        $this->logger->error($message, $context);
    }

    public function debug(string|\Stringable $message, array $context = []): void
    {
        $this->logger->debug($message, $context);
    }

    private static function createLogDirectoryIfNotExists(): void
    {
        if (!file_exists('logs')
            || (
                file_exists('logs') &&
                is_file('logs') &&
                unlink('logs')
            )
        ) {
            mkdir('logs');
        }

        file_exists('logs') && is_dir('logs');
    }
}
