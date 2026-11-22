<?php

namespace App\Filament\Widgets;

use App\Services\Outreach\MessageRenderer;
use App\Support\Reports;
use Filament\Widgets\Concerns\InteractsWithPageFilters;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class RevenueStats extends StatsOverviewWidget
{
    use InteractsWithPageFilters;

    protected static ?int $sort = 4;

    protected int|string|array $columnSpan = 'full';

    protected function getColumns(): int
    {
        return 4;
    }

    protected function getStats(): array
    {
        $r = (new Reports($this->filters ?? []))->revenue();
        $tl = fn (int $v) => MessageRenderer::money($v).' ₺';

        return [
            Stat::make('Aylık tekrarlayan gelir (MRR)', $tl($r['mrr']))->description("{$r['deals']} satış")->color('success'),
            Stat::make('Tahsil edilen kurulum', $tl($r['setup_collected'])),
            Stat::make('Bu ay tahsilat', $tl($r['collected_this_month'])),
            Stat::make('Bekleyen ödeme', $tl($r['outstanding']))->color($r['outstanding'] > 0 ? 'danger' : 'gray'),
        ];
    }
}
