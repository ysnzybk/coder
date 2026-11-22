<?php

namespace App\Filament\Widgets;

use App\Support\Reports;
use Filament\Widgets\ChartWidget;
use Filament\Widgets\Concerns\InteractsWithPageFilters;

class FunnelChart extends ChartWidget
{
    use InteractsWithPageFilters;

    protected static ?string $heading = 'Satış hunisi';

    protected static ?int $sort = 2;

    protected static ?string $maxHeight = '280px';

    protected function getData(): array
    {
        $funnel = (new Reports($this->filters ?? []))->funnel();

        return [
            'datasets' => [[
                'label' => 'Firma',
                'data' => array_values($funnel),
                'backgroundColor' => '#d97706',
                'borderRadius' => 6,
            ]],
            'labels' => array_keys($funnel),
        ];
    }

    protected function getOptions(): array
    {
        return ['indexAxis' => 'y', 'plugins' => ['legend' => ['display' => false]]];
    }

    protected function getType(): string
    {
        return 'bar';
    }
}
