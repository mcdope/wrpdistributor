<?php

declare(strict_types = 1);

namespace AmiDev\WrpDistributor\Commands\Statistics;

use AmiDev\WrpDistributor\Commands\Command;
use AmiDev\WrpDistributor\Statistics;
use Symfony\Component\Console\Exception\InvalidArgumentException;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

final class DashboardUpdate extends Command
{
    private const DASHBOARD_TEMPLATE = /** @lang HTML */
        <<<TPL
<!DOCTYPE html>
<html lang="en">
    <head>
        <meta charset="utf-8">
        <title>AmiFox Distributor Dashboard - Clients total: %sessionsAlltime%</title>
        <script src="https://cdn.jsdelivr.net/npm/chart.js@^3"></script>
        <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha3/dist/css/bootstrap.min.css" 
              rel="stylesheet" 
              integrity="sha384-KK94CHFLLe+nY2dmCWGMq91rCGa5gtU4mk92HdvYe+M/SXH301p5ILy+dN9+nJOZ" 
              crossorigin="anonymous"
        >
        <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha3/dist/js/bootstrap.bundle.min.js" 
                integrity="sha384-ENjdO4Dr2bkBIFxQpeoTz1HIcje39Wm4jDKdf19U8gI4ddQ3GYNS7NTKfAdVQSZe" 
                crossorigin="anonymous"
        ></script>
        
        <style>
            canvas {
                width: 100%;
                height: 100%;
                display: block;
            }
            
            div.chart {
                padding: 1px;
            }
        </style>
    </head>
    <body>
        <div class="row mb-3 text-center">
            <div class="col-md-8 themed-grid-col">
                <h3>Containers per host (<abbr title="All times are UTC">now</abbr> -%daysToShow% days)</h3>
                <div id="containersPerHostContainer" class="chart">
                    <canvas id="containersPerHostCanvas" class="canvas-wide"></canvas>
                </div>
            </div>
            <div class="col-md-4 themed-grid-col">
                <h3>Container usage total (<abbr title="Currently configured maximum container count over all hosts">of %maxContainers%</abbr>)</h3>
                <div id="containersRemainingContainer" class="chart">
                    <canvas id="containersRemainingCanvas"></canvas>
                </div>
            </div>
        </div>
        <div class="row mb-3 text-center">
            <div class="col-md-8 themed-grid-col">
                <h3>Sessions, unique clients &amp; containers total (<abbr title="All times are UTC">now</abbr> -%daysToShow% days)</h3>
                <div id="sessionTotalsContainer" class="chart">
                    <canvas id="sessionTotalsCanvas" class="canvas-wide"></canvas>
                </div>
            </div>
            <div class="col-md-4 themed-grid-col">
                <h3>Currently active sessions</h3>
                <div id="activeSessionsContainer" class="chart">
                    <canvas id="activeSessionsCanvas"></canvas>
                </div>
            </div>
        </div>
        <div class="row mb-3 text-center">
            <div class="col-md-6 themed-grid-col">
                <h3>Sessions, <abbr title="This was introduced after launch, so it isn't available for the first weeks">unique clients</abbr> &amp; containers total by date</h3>
                <div id="totalsByDateContainer" class="chart">
                    <canvas id="totalsByDateCanvas" class="canvas-wide"></canvas>
                </div>
            </div>
            <div class="col-md-6 themed-grid-col">
                <h3>Sessions, <abbr title="This was introduced after launch, so it isn't available for the first weeks">unique clients</abbr> &amp; containers total by month</h3>
                <div id="totalsByMonthContainer" class="chart">
                    <canvas id="totalsByMonthCanvas" class="canvas-wide"></canvas>
                </div>
            </div>
        </div>
        
        <script>
        const containersPerHost = document.getElementById('containersPerHostCanvas');
        const containersRemaining = document.getElementById('containersRemainingCanvas');
        const sessionTotals = document.getElementById('sessionTotalsCanvas');
        const activeSessions = document.getElementById('activeSessionsCanvas');
        const totalsByDate = document.getElementById('totalsByDateCanvas');
        const totalsByMonth = document.getElementById('totalsByMonthCanvas');
        
        const containersPerHostLabels = %containersPerHostLabels%;
        const containersPerHostDatasets = %containersPerHostDatasets%;
        const totalLabels = %totalLabels%;
        const totalDatasets = %totalDatasets%;
        const totalSessions = %totalSessions%;
        const totalContainers = %totalContainers%;
        const maxContainers = %maxContainers%;
        const sessionsAlltime = %sessionsAlltime%;
        const containersMaxConcurrent = %containersMaxConcurrent%;
        const totalsByDateLabels = %totalsByDateLabels%;
        const totalsByDateDatasets = %totalsByDateDatasets%;
        const totalsByMonthLabels = %totalsByMonthLabels%;
        const totalsByMonthDatasets = %totalsByMonthDatasets%;
        
        // slot 1, top left
        const slot1 = new Chart(containersPerHost, {
            type: 'line',
            data: {
                labels: containersPerHostLabels,
                datasets: containersPerHostDatasets
            },
            options: {
                responsive: true,
                maintainAspectRatio: true,
                scales: {
                    y: {
                        beginAtZero: true,
                        ticks: {
                            stepSize: 1
                        }
                    },
                    x: {
                        ticks: {
                            autoSkip: true,
                            maxRotation: 90,
                            minRotation: 90
                        }
                    }
                }
            }
        });
        slot1.options.plugins.decimation.algorithm = 'min-max';
        slot1.options.plugins.decimation.enabled = false;
        slot1.options.plugins.decimation.samples = 500;
        slot1.update()
        
        // slot 2, top right
        new Chart(containersRemaining, {
            type: 'pie',
            data: {
                labels: ['Never used / scaling reserve', 'Currently in use', 'Max concurrent containers so far'],
                datasets: [
                    {
                        data: [
                            (maxContainers-(totalContainers > containersMaxConcurrent ? totalContainers : 0)-containersMaxConcurrent), 
                            totalContainers,
                            containersMaxConcurrent
                        ],
                        backgroundColor: [
                            'rgb(0, 255, 0)',
                            'rgb(255, 255, 0)',
                            'rgb(255, 165, 0)',
                        ]
                    }
                ]
            },
            options: {
                responsive: true,
                maintainAspectRatio: true,
                plugins: {
                    legend: {
                        position: 'bottom',
                    },
                }
            },
        });
        
        // slot 3, bottom left
        const slot3 = new Chart(sessionTotals, {
            type: 'line',
            data: {
                labels: totalLabels,
                datasets: totalDatasets
            },
            options: {
                responsive: true,
                maintainAspectRatio: true,
                scales: {
                    y: {
                        beginAtZero: true,
                        ticks: {
                            stepSize: 1
                        }
                    },
                    x: {
                        ticks: {
                            autoSkip: true,
                            maxRotation: 90,
                            minRotation: 90
                        }
                    }
                }
            }
        });
        slot3.options.plugins.decimation.algorithm = 'min-max';
        slot3.options.plugins.decimation.enabled = false;
        slot3.options.plugins.decimation.samples = 500;
        slot3.update()
        
        // slot 4, bottom right
        if ((totalSessions-totalContainers) || totalContainers) {
            new Chart(activeSessions, {
                type: 'pie',
                data: {
                    labels: ['No container', 'With container'],
                    datasets: [
                        {
                            data: [
                              (totalSessions-totalContainers), 
                              totalContainers
                            ],
                            backgroundColor: [
                              'rgb(255, 255, 0)',
                              'rgb(0, 255, 0)'
                            ]
                        }
                    ]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: true,
                    plugins: {
                        legend: {
                              position: 'bottom',
                        },
                    }
                },
            });
        } else {
            document.querySelector('div#activeSessionsContainer').style.display = 'none'
        }
        
        // slot 5, left
        const slot5 = new Chart(totalsByDate, {
            type: 'line',
            data: {
                labels: totalsByDateLabels,
                datasets: totalsByDateDatasets
            },
            options: {
                responsive: true,
                maintainAspectRatio: true,
                scales: {
                    y: {
                        beginAtZero: true,
                        ticks: {
                            stepSize: 1
                        }
                    },
                    x: {
                        ticks: {
                            autoSkip: true,
                            maxRotation: 90,
                            minRotation: 90
                        }
                    }
                }
            }
        });
        slot5.options.plugins.decimation.algorithm = 'min-max';
        slot5.options.plugins.decimation.enabled = false;
        slot5.options.plugins.decimation.samples = 500;
        slot5.update();

        // slot 6, right
        const slot6 = new Chart(totalsByMonth, {
            type: 'line',
            data: {
                labels: totalsByMonthLabels,
                datasets: totalsByMonthDatasets
            },
            options: {
                responsive: true,
                maintainAspectRatio: true,
                scales: {
                    y: {
                        beginAtZero: true,
                        ticks: {
                            stepSize: 1
                        }
                    },
                    x: {
                        ticks: {
                            autoSkip: true,
                            maxRotation: 90,
                            minRotation: 90
                        }
                    }
                }
            }
        });
        slot6.options.plugins.decimation.algorithm = 'min-max';
        slot6.options.plugins.decimation.enabled = false;
        slot6.options.plugins.decimation.samples = 500;
        slot6.update();

        let reloadInterval = null;
        function enableAutoReload() {
            reloadInterval = setInterval(
                function() { 
                    window.location=window.location;
                },
                30000
            );
        }
        
        enableAutoReload();
        document.addEventListener("visibilitychange", function() {
            if (document.hidden){
                clearInterval(reloadInterval);
            } else {
                enableAutoReload();
            }
        });
        </script>
    </body>
</html>
TPL;

    private function getLineChartDatasetTemplate(): array
    {
        return [
            'label' => '',
            'data' => [],
            'fill' => true,
            'borderColor' => $this->randomRgbCssColorCode(),
            'tension' => 0.1,
            'pointStyle' => false,
            'pointRadius' => 0,
            'borderWidth' => 1,
        ];
    }

    /**
     * @return string[]
     *
     * @throws \JsonException
     */
    private function createContainersPerHostChart(int $daysToShow): array
    {
        $labels = $datasets = [];
        $dataPoints = $this->serviceContainer->statistics->getContainerHostUsageForTimeframe(new \DateTime('-' . (string) $daysToShow . ' days'));
        foreach ($dataPoints as $timeOfCapture => $hostAndCountPerPoint) {
            $labels[] = (new \DateTime($timeOfCapture))->format('H:i');

            foreach ($hostAndCountPerPoint as $containersByHost) {
                foreach ($containersByHost as $host => $containerCount) {
                    if (!array_key_exists($host, $datasets)) {
                        $datasets[$host] = $this->getLineChartDatasetTemplate();
                        $datasets[$host]['label'] = $host;
                        $datasets[$host]['borderColor'] = $this->randomRgbCssColorCode();
                    }

                    $datasets[$host]['data'][] = $containerCount;
                }
            }
        }

        $jsLabels = '[';
        foreach ($labels as $label) {
            $jsLabels .= "'" . $label . "',";
        }
        $jsLabels = substr($jsLabels, 0, -1) . ']';

        $jsDatasets = '[';
        foreach ($datasets as $dataset) {
            $jsDatasets .= json_encode($dataset, JSON_THROW_ON_ERROR) . ',';
        }
        $jsDatasets = substr($jsDatasets, 0, -1) . ']';

        return [$jsLabels, $jsDatasets];
    }

    /**
     * @return string[]
     *
     * @throws \JsonException
     */
    private function createTotalsChart(int $daysToShow): array
    {
        $labels = $datasets = [];
        $dataPoints = $this->serviceContainer->statistics->getTotalsForTimeframe(new \DateTime('-' . (string) $daysToShow . ' days'));
        foreach ($dataPoints as $dataPoint) {
            $labels[] = (new \DateTime($dataPoint['timeOfCapture']))->format('H:i');
            foreach ($dataPoint as $valueName => $singleValue) {
                if (is_numeric($valueName) || 'timeOfCapture' === $valueName) {
                    continue;
                }

                if (!array_key_exists($valueName, $datasets)) {
                    $datasets[$valueName] = $this->getLineChartDatasetTemplate();
                    $datasets[$valueName]['label'] = $valueName;
                    $datasets[$valueName]['borderColor'] = $this->randomRgbCssColorCode();
                }

                $datasets[$valueName]['data'][] = $singleValue;
            }
        }

        $jsLabels = '[';
        foreach ($labels as $label) {
            $jsLabels .= "'" . $label . "',";
        }
        $jsLabels = substr($jsLabels, 0, -1) . ']';

        $jsDatasets = '[';
        foreach ($datasets as $dataset) {
            $jsDatasets .= json_encode($dataset, JSON_THROW_ON_ERROR) . ',';
        }
        $jsDatasets = substr($jsDatasets, 0, -1) . ']';

        return [$jsLabels, $jsDatasets];
    }

    /**
     * @return string[]
     *
     * @throws \JsonException
     */
    private function createGroupedTotalsChart(string $groupByConstValue): array
    {
        $labels = $datasets = [];
        $dataPoints = $this->serviceContainer->statistics->getSummarizedAndAveragedStatistics($groupByConstValue);
        foreach ($dataPoints as $dataPoint) {
            if (Statistics::GROUPBY_MONTH === $groupByConstValue) {
                $labels[] = (
                    \DateTime::createFromFormat(
                        '!m',
                        (string) $dataPoint['timeOfCapture']
                    )
                )->format('F');
            } else {
                $labels[] = $dataPoint['timeOfCapture'];
            }

            foreach ($dataPoint as $valueName => $singleValue) {
                if (is_numeric($valueName) || 'timeOfCapture' === $valueName) {
                    continue;
                }

                if (!array_key_exists($valueName, $datasets)) {
                    $datasets[$valueName] = $this->getLineChartDatasetTemplate();
                    $datasets[$valueName]['label'] = $valueName;
                    $datasets[$valueName]['borderColor'] = $this->randomRgbCssColorCode();
                }

                $datasets[$valueName]['data'][] = $singleValue;
            }
        }

        $jsLabels = '[';
        foreach ($labels as $label) {
            $jsLabels .= "'" . $label . "',";
        }
        $jsLabels = substr($jsLabels, 0, -1) . ']';

        $jsDatasets = '[';
        foreach ($datasets as $dataset) {
            $jsDatasets .= json_encode($dataset, JSON_THROW_ON_ERROR) . ',';
        }
        $jsDatasets = substr($jsDatasets, 0, -1) . ']';

        return [$jsLabels, $jsDatasets];
    }

    /**
     * @noinspection PhpUnused
     *
     * @throws InvalidArgumentException
     */
    protected function configure(): void
    {
        $this->setName('statistics:dashboard:update');
        $this->setDescription('Writes fresh dashboard.html file');
    }

    /**
     * @noinspection PhpUnused
     *
     * @throws InvalidArgumentException
     */
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        try {
            [
                $jsContainersPerHostLabels,
                $jsContainersPerHostDatasets
            ] = $this->createContainersPerHostChart(1);

            [
                $jsTotalLabels,
                $jsTotalDatasets
            ] = $this->createTotalsChart(1);

            [
                $jsTotalByDateLabels,
                $jsTotalByDateDatasets
            ] = $this->createGroupedTotalsChart(Statistics::GROUPBY_DATE);

            [
                $jsTotalByMonthLabels,
                $jsTotalByMonthDatasets
            ] = $this->createGroupedTotalsChart(Statistics::GROUPBY_MONTH);

            $htmlOutput = str_replace(
                [
                    '%containersPerHostLabels%',
                    '%containersPerHostDatasets%',
                    '%totalSessions%',
                    '%totalContainers%',
                    '%maxContainers%',
                    '%totalLabels%',
                    '%totalDatasets%',
                    '%sessionsAlltime%',
                    '%containersMaxConcurrent%',
                    '%daysToShow%',
                    '%totalsByMonthLabels%',
                    '%totalsByMonthDatasets%',
                    '%totalsByDateLabels%',
                    '%totalsByDateDatasets%',
                ],
                [
                    $jsContainersPerHostLabels,
                    $jsContainersPerHostDatasets,
                    $this->serviceContainer->pdo->query('SELECT COUNT(`id`) FROM `sessions`')->fetch()[0],
                    $this->serviceContainer->dockerManager->countsPortsUsed(),
                    $this->serviceContainer->dockerManager->countTotalMaxContainers(),
                    $jsTotalLabels,
                    $jsTotalDatasets,
                    $this->serviceContainer->statistics->getTotalSessionsServed(),
                    $this->serviceContainer->statistics->getMaxConcurrentContainersServed(),
                    1,
                    $jsTotalByMonthLabels,
                    $jsTotalByMonthDatasets,
                    $jsTotalByDateLabels,
                    $jsTotalByDateDatasets,
                ],
                self::DASHBOARD_TEMPLATE,
            );

            file_put_contents('dashboard.html', $htmlOutput);
        } catch (\Throwable $throwable) {
            $this->serviceContainer->logger->warning(
                sprintf('Throwable occurred in %s', self::class),
                [
                    'message' => $throwable->getMessage(),
                    'trace' => $throwable->getTrace(),
                ],
            );

            return self::FAILURE;
        }

        return self::SUCCESS;
    }

    private function randomRgbCssColorCode(): string
    {
        return 'rgb(' . (string) rand(0, 255) . ',' . (string) rand(0, 255) . ',' . (string) rand(0, 255) . ')';
    }
}
