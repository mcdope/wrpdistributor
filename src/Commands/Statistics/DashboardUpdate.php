<?php

declare(strict_types = 1);

namespace AmiDev\WrpDistributor\Commands\Statistics;

use AmiDev\WrpDistributor\Commands\Command;
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
        
        <style type="text/css">
            table {
                width: 100%;
                height: 100%;
                border: none;
            }
            
            tr {
                height: 49%;
            }
            
            tr > td:first-of-type {
                width: 80%;
            }
            
            td {
                vertical-align: top;
            }
            
            td > div {
                vertical-align: center;
                text-align: center;
            }
            
            .canvas-wide {
                width: 65%!important;
            }
            
            div#global-stats {
                float: right;
            }
        </style>
  </head>
  <body>
        <table>
            <tr>
                <td>
                    <div id="containersPerHostContainer" class="chart">
                        <h3>Containers per Host (now -7 days)</h3>
                        <canvas id="containersPerHostCanvas" class="canvas-wide"></canvas>
                    </div>
                </td>
                <td>
                    <div id="containersRemainingContainer" class="chart">
                        <h3>Container usage total (<abbr title="Currently configured maximum container count over all hosts">of %maxContainers%</abbr>)</h3>
                        <canvas id="containersRemainingCanvas"></canvas>
                    </div>
                </td>
            </tr>
            <tr>
                <td>
                    <div id="sessionTotalsContainer" class="chart">
                        <h3>Sessions &amp; containers total (now -7 days)</h3>
                        <canvas id="sessionTotalsCanvas" class="canvas-wide"></canvas>
                    </div>
                </td>
                <td>
                    <div id="activeSessionsContainer" class="chart">
                        <h3>Currently active sessions</h3>
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
              const sessionsAlltime = %sessionsAlltime%;
              const containersMaxConcurrent = %containersMaxConcurrent%;
              
              // slot 1, top left
              const slot1 = new Chart(containersPerHost, {
                    type: 'line',
                    data: {
                          labels: containersPerHostLabels,
                          datasets: containersPerHostDatasets
                    },
                    options: {
                          responsive: false,
                          maintainAspectRatio: false,
                          scales: {
                                y: {
                                    beginAtZero: true,
                                    ticks: {
                                       stepSize: 1
                                    }
                                },
                          }
                    }
              });
              slot1.options.plugins.decimation.algorithm = 'min-max';
              slot1.options.plugins.decimation.enabled = true;
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
                                      (maxContainers-totalContainers-containersMaxConcurrent), 
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
                        responsive: false,
                        maintainAspectRatio: false,
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
                          responsive: false,
                          maintainAspectRatio: false,
                          scales: {
                                y: {
                                    beginAtZero: true,
                                    ticks: {
                                       stepSize: 1
                                    }
                                }
                          }
                    }
              });
              slot3.options.plugins.decimation.algorithm = 'min-max';
              slot3.options.plugins.decimation.enabled = true;
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
                            responsive: false,
                            maintainAspectRatio: false,
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
    private function createContainersPerHostChart(): array
    {
        $labels = $datasets = [];
        $dataPoints = $this->serviceContainer->statistics->getContainerHostUsageForTimeframe(new \DateTime('-7 days'));
        foreach ($dataPoints as $hostAndCountPerPoint) {
            $labels[] = '';
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
    private function createTotalsChart(): array
    {
        $labels = $datasets = [];
        $dataPoints = $this->serviceContainer->statistics->getTotalsForTimeframe(new \DateTime('-7 days'));
        foreach ($dataPoints as $dataPoint) {
            $labels[] = '';
            foreach ($dataPoint as $valueName => $singleValue) {
                if (is_numeric($valueName) || 'timeOfCapture' === $valueName || 'Remaining containers' === $valueName) {
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
                    '%sessionsAlltime%',
                    '%containersMaxConcurrent%',
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
                ],
                self::DASHBOARD_TEMPLATE,
            );

            file_put_contents('dashboard.html', $htmlOutput);

            return self::SUCCESS;
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
    }

    private function randomRgbCssColorCode(): string
    {
        return 'rgb(' . (string) rand(0, 255) . ',' . (string) rand(0, 255) . ',' . (string) rand(0, 255) . ')';
    }
}
