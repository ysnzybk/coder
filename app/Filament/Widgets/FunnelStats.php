<?php

namespace App\Filament\Widgets;

use App\Support\Reports;
use Filament\Widgets\Concerns\InteractsWithPageFilters;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class FunnelStats extends StatsOverviewWidget
{
    use InteractsWithPageFilters;

    protected static ?int $sort = 1;

    protected int|string|array $columnSpan = 'full';

    protected function getColumns(): int
    {
        return 4;
    }

    protected function getStats(): array
    {
        $r = new Reports($this->filters ?? []);
        $f = array_values($r->funnel());
        [$found, $target, $sites, $sent, $viewed, $replied, $won] = $f;

        return [
            Stat::make('Hedef firma (sitesi yok)', number_format($target, 0, ',', '.'))
                ->description("Toplam bulunan: {$found}")->color('gray'),
            Stat::make('Gönderilen teklif', number_format($sent, 0, ',', '.'))
                ->description("Üretilen site: {$sites}")->color('info'),
            Stat::make('Siteyi açan', number_format($viewed, 0, ',', '.'))
                ->description('Açılma oranı: '.Reports::rate($viewed, $sent))->color('warning'),
            Stat::make('Satış', number_format($won, 0, ',', '.'))
                ->description('Dönüşüm (gönderilen→satış): '.Reports::rate($won, $sent).' · İlgili: '.$replied)->color('success'),
        ];
    }
}
