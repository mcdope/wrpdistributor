<?php

declare(strict_types = 1);

namespace Tests;

use AmiDev\WrpDistributor\Logger;

final class LoggerTest extends BaseTestCase
{
    public function testInfoLogsLikeExpected(): void
    {
        $monolog = $this->createMock(\Monolog\Logger::class);

        $monolog
            ->expects(self::once())
            ->method('pushHandler')
        ;

        $monolog
            ->expects(self::once())
            ->method('info')
            ->with('test', ['elem1' => 'val1', 'elem2' => 'val2'])
        ;

        $testLogger = new Logger($monolog);
        $testLogger->info('test', ['elem1' => 'val1', 'elem2' => 'val2']);
    }

    public function testWarningLogsLikeExpected(): void
    {
        $monolog = $this->createMock(\Monolog\Logger::class);

        $monolog
            ->expects(self::once())
            ->method('pushHandler')
        ;

        $monolog
            ->expects(self::once())
            ->method('warning')
            ->with('test', ['elem1' => 'val1', 'elem2' => 'val2'])
        ;

        $testLogger = new Logger($monolog);
        $testLogger->warning('test', ['elem1' => 'val1', 'elem2' => 'val2']);
    }

    public function testErrorLogsLikeExpected(): void
    {
        $monolog = $this->createMock(\Monolog\Logger::class);

        $monolog
            ->expects(self::once())
            ->method('pushHandler')
        ;

        $monolog
            ->expects(self::once())
            ->method('error')
            ->with('test', ['elem1' => 'val1', 'elem2' => 'val2'])
        ;

        $testLogger = new Logger($monolog);
        $testLogger->error('test', ['elem1' => 'val1', 'elem2' => 'val2']);
    }

    public function testDebugLogsLikeExpected(): void
    {
        $monolog = $this->createMock(\Monolog\Logger::class);

        $monolog
            ->expects(self::once())
            ->method('pushHandler')
        ;

        $monolog
            ->expects(self::once())
            ->method('debug')
            ->with('test', ['elem1' => 'val1', 'elem2' => 'val2'])
        ;

        $testLogger = new Logger($monolog);
        $testLogger->debug('test', ['elem1' => 'val1', 'elem2' => 'val2']);
    }

    public function testLogsEvenWithNonExistingLogDirectory(): void
    {
        $monolog = $this->createMock(\Monolog\Logger::class);

        // Cheat "non-existing" log dir
        $cwd = getcwd();
        self::assertTrue(chdir('/tmp'));

        $monolog
            ->expects(self::once())
            ->method('pushHandler')
        ;

        $monolog
            ->expects(self::once())
            ->method('info')
            ->with('test', ['elem1' => 'val1', 'elem2' => 'val2'])
        ;

        $testLogger = new Logger($monolog);
        $testLogger->info('test', ['elem1' => 'val1', 'elem2' => 'val2']);

        self::assertTrue(chdir($cwd));
    }
}
