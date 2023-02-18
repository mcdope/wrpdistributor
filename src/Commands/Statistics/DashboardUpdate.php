<?php

declare(strict_types=1);

namespace AmiDev\WrpDistributor\Commands\Statistics;

use AmiDev\WrpDistributor\Commands\Command;
use Symfony\Component\Console\Exception\InvalidArgumentException;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

final class DashboardUpdate extends Command
{
    private const DASHBOARD_TEMPLATE = <<<TPL
<!DOCTYPE html>
<html lang="en">
  <head>
        <meta charset="utf-8">
        <title>AmiFox Distributor Dashboard</title>
        <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
        
        <style type="text/css">
            table {
                width: 100%;
                height: 100%;
                border: none;
            }
            
            td {
                vertical-align: top;
            }
            
            td > div {
                vertical-align: center;
                text-align: center;
            }
        </style>
  </head>
  <body>
        <table>
            <tr>
                <td>
                    <div id="containersPerHostContainer" class="chart">
                        <h3>Containers per Host</h3>
                        <canvas id="containersPerHostCanvas"></canvas>
                    </div>
                </td>
                <td>
                    <div id="containersRemainingContainer" class="chart">
                        <h3>Container usage</h3>
                        <canvas id="containersRemainingCanvas"></canvas>
                    </div>
                </td>
            </tr>
            <tr>
                <td>
                    <div id="sessionTotalsContainer" class="chart">
                        <h3>Sessions &amp; containers total</h3>
                        <canvas id="sessionTotalsCanvas"></canvas>
                    </div>
                </td>
                <td>
                    <div id="activeSessionsContainer" class="chart">
                        <h3>Active sessions right now</h3>
                        <canvas id="activeSessionsCanvas"></canvas>
                    </div>
                </td>
            </tr>
        </table>
        
        <script>
              const containersPerHost = document.getElementById('containersPerHostCanvas');
              const containersRemaining = document.getElementById('containersRemainingCanvas');
              const sessionTotals = document.getElementById('sessionTotalsCanvas');
              const activeSessions = document.getElementById('activeSessionsCanvas');
              
              const containersPerHostLabels = %containersPerHostLabels%;
              const containersPerHostDatasets = %containersPerHostDatasets%;
              const totalLabels = %totalLabels%;
              const totalDatasets = %totalDatasets%;
              const totalSessions = %totalSessions%;
              const totalContainers = %totalContainers%;
              const maxContainers = %maxContainers%;
              
              // slot 1, top left
              new Chart(containersPerHost, {
                    type: 'line',
                    data: {
                          labels: containersPerHostLabels,
                          datasets: containersPerHostDatasets
                    },
                    options: {
                          responsive: true,
                          scales: {
                                y: {
                                    beginAtZero: true
                                }
                          }
                    }
              });
              
              // slot 2, top right
              new Chart(containersRemaining, {
                  type: 'pie',
                  data: {
                      labels: ['Remaining', 'In use'],
                      datasets: [
                            {
                                  data: [
                                      (maxContainers-totalContainers), 
                                      totalContainers
                                  ],
                                  backgroundColor: [
                                      'rgb(0, 255, 0)',
                                      'rgb(255, 255, 0)'
                                  ]
                            }
                      ]
                  },
                  options: {
                        responsive: true,
                        plugins: {
                            legend: {
                                  position: 'bottom',
                            },
                        }
                  },
              });
              
              // slot 3, bottom left
              new Chart(sessionTotals, {
                    type: 'line',
                    data: {
                          labels: totalLabels,
                          datasets: totalDatasets
                    },
                    options: {
                          responsive: true,
                          scales: {
                                y: {
                                    beginAtZero: true
                                }
                          }
                    }
              });
              
              // slot 4, bottom right
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
                            }
                      ]
                  },
                  options: {
                        responsive: true,
                        plugins: {
                            legend: {
                                  position: 'bottom',
                            },
                        }
                  },
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
            'tension' => 0.1
        ];
    }

    /**
     * @return string[]
     * @throws \JsonException
     */
    private function createContainersPerHostChart(): array
    {
        $labels = $datasets = [];
        $dataPoints = $this->serviceContainer->statistics->getContainerHostUsageForTimeframe();
        foreach ($dataPoints as $hostAndCountPerPoint) {
            $labels[] = '';
            $this->serviceContainer->logger->debug('hostAndCount', $hostAndCountPerPoint);
            foreach ($hostAndCountPerPoint as $containersByHost) {
                $this->serviceContainer->logger->debug('containersByHost', $containersByHost);
                foreach ($containersByHost as $host => $containerCount) {
                    $this->serviceContainer->logger->debug('containerCount', ['count' => $containerCount]);

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
     * @throws \JsonException
     */
    private function createTotalsChart(): array
    {
        $labels = $datasets = [];
        $dataPoints = $this->serviceContainer->statistics->getTotalsForTimeframe();
        foreach ($dataPoints as $dataPoint) {
            $labels[] = '';
            $this->serviceContainer->logger->debug('dataPoint', $dataPoint);
            foreach ($dataPoint as $valueName => $singleValue) {
                if (is_numeric($valueName) || 'timeOfCapture' === $valueName) {
                    continue;
                }

                $this->serviceContainer->logger->debug('valueName', ['valueName' => $valueName]);
                $this->serviceContainer->logger->debug('singleValue', ['singleValue' => $singleValue]);

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
            ] = $this->createContainersPerHostChart();

            [
                $jsTotalLabels,
                $jsTotalDatasets
            ] = $this->createTotalsChart();

            $htmlOutput = str_replace(
                [
                    '%containersPerHostLabels%',
                    '%containersPerHostDatasets%',
                    '%totalSessions%',
                    '%totalContainers%',
                    '%maxContainers%',
                    '%totalLabels%',
                    '%totalDatasets%',
                ],
                [
                    $jsContainersPerHostLabels,
                    $jsContainersPerHostDatasets,
                    $this->serviceContainer->pdo->query('SELECT COUNT(`id`) FROM `sessions`')->fetch()[0],
                    $this->serviceContainer->dockerManager->countsPortsUsed(),
                    $this->serviceContainer->dockerManager->countTotalMaxContainers(),
                    $jsTotalLabels,
                    $jsTotalDatasets
                ],
                self::DASHBOARD_TEMPLATE
            );

            file_put_contents('dashboard.html', $htmlOutput);
            return self::SUCCESS;
        } catch (\Throwable $throwable) {
            $this->serviceContainer->logger->debug(
                sprintf('Throwable occurred in %s', self::class),
                [
                    'message' => $throwable->getMessage(),
                    'trace' => $throwable->getTrace(),
                ]
            );

            return self::FAILURE;
        }
    }

    private function randomRgbCssColorCode(): string
    {
        return 'rgb(' . (string) rand(0, 255) . ',' . (string) rand(0, 255) . ',' . (string) rand(0, 255) . ')';
    }
}
