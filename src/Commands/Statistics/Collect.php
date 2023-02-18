<?php

declare(strict_types=1);

namespace AmiDev\WrpDistributor\Commands\Statistics;

use AmiDev\WrpDistributor\Commands\Command;
use Symfony\Component\Console\Exception\InvalidArgumentException;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

final class Collect extends Command
{

    /**
     * @noinspection PhpUnused
     *
     * @throws InvalidArgumentException
     */
    protected function configure(): void
    {
        $this->setName('statistics:collect');
        $this->setDescription('Collects current statistics');
    }

    /**
     * @noinspection PhpUnused
     *
     * @throws InvalidArgumentException
     */
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $dataPoint = $this->serviceContainer->statistics->collectDatapoint();
        $this->serviceContainer->statistics->insert($dataPoint);

        return self::SUCCESS;
    }
}
