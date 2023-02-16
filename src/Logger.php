<?php

/** @noinspection PhpMultipleClassDeclarationsInspection */

namespace AmiDev\WrpDistributor;

use Monolog\ErrorHandler;
use Monolog\Handler\RotatingFileHandler;
use Monolog\Logger as MonologLogger;

class Logger
{
    private MonologLogger $logger;

    public function __construct()
    {
        $log = new MonologLogger('distributor');
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
}