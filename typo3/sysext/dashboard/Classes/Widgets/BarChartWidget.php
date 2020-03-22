<?php
declare(strict_types=1);
namespace TYPO3\CMS\Dashboard\Widgets;

/*
 * This file is part of the TYPO3 CMS project.
 *
 * It is free software; you can redistribute it and/or modify it under
 * the terms of the GNU General Public License, either version 2
 * of the License, or any later version.
 *
 * For the full copyright and license information, please read the
 * LICENSE.txt file that was distributed with this source code.
 *
 * The TYPO3 project - inspiring people to share!
 */

use TYPO3\CMS\Dashboard\Utility\ButtonUtility;
use TYPO3\CMS\Dashboard\Widgets\Interfaces\AdditionalCssInterface;
use TYPO3\CMS\Dashboard\Widgets\Interfaces\ButtonProviderInterface;
use TYPO3\CMS\Dashboard\Widgets\Interfaces\ChartDataProviderInterface;
use TYPO3\CMS\Dashboard\Widgets\Interfaces\EventDataInterface;
use TYPO3\CMS\Dashboard\Widgets\Interfaces\RequireJsModuleInterface;
use TYPO3\CMS\Dashboard\Widgets\Interfaces\WidgetConfigurationInterface;
use TYPO3\CMS\Dashboard\Widgets\Interfaces\WidgetInterface;
use TYPO3\CMS\Fluid\View\StandaloneView;

/**
 * Concrete Bar Chart widget implementation
 *
 * Shows a widget with a bar chart. The data for this chart will be provided by the data provider you will set.
 * You can add a button to the widget by defining a button provider.
 *
 * There are no options available for this widget
 *
 * @see ChartDataProviderInterface
 * @see ButtonProviderInterface
 */
class BarChartWidget implements WidgetInterface, EventDataInterface, AdditionalCssInterface, RequireJsModuleInterface
{
    /**
     * @var WidgetConfigurationInterface
     */
    private $configuration;

    /**
     * @var ChartDataProviderInterface
     */
    private $dataProvider;

    /**
     * @var StandaloneView
     */
    private $view;

    /**
     * @var ButtonProviderInterface|null
     */
    private $buttonProvider;

    /**
     * @var array
     */
    private $options;

    public function __construct(
        WidgetConfigurationInterface $configuration,
        ChartDataProviderInterface $dataProvider,
        StandaloneView $view,
        $buttonProvider = null,
        array $options = []
    ) {
        $this->configuration = $configuration;
        $this->dataProvider = $dataProvider;
        $this->view = $view;
        $this->options = $options;
        $this->buttonProvider = $buttonProvider;
    }

    public function renderWidgetContent(): string
    {
        $this->view->setTemplate('Widget/ChartWidget');
        $this->view->assignMultiple([
            'button' => ButtonUtility::generateButtonConfig($this->buttonProvider),
            'options' => $this->options,
            'configuration' => $this->configuration,
        ]);
        return $this->view->render();
    }

    public function getEventData(): array
    {
        return [
            'graphConfig' => [
                'type' => 'bar',
                'options' => [
                    'maintainAspectRatio' => false,
                    'legend' => [
                        'display' => false,
                    ],
                    'scales' => [
                        'yAxes' => [
                            [
                                'ticks' => [
                                    'beginAtZero' => true,
                                ],
                            ],
                        ],
                        'xAxes' => [
                            [
                                'ticks' => [
                                    'maxTicksLimit' => 15,
                                ],
                            ],
                        ],
                    ],
                ],
                'data' => $this->dataProvider->getChartData(),
            ],
        ];
    }

    public function getCssFiles(): array
    {
        return ['EXT:dashboard/Resources/Public/Css/Contrib/chart.css'];
    }

    public function getRequireJsModules(): array
    {
        return [
            'TYPO3/CMS/Dashboard/Contrib/chartjs',
            'TYPO3/CMS/Dashboard/ChartInitializer',
        ];
    }
}
