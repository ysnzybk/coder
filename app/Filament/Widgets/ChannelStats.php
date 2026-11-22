<?php

namespace App\Filament\Widgets;

use App\Support\Reports;
use Filament\Widgets\Concerns\InteractsWithPageFilters;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class ChannelStats extends StatsOverviewWidget
{
    use InteractsWithPageFilters;

    protected static ?int $sort = 3;

    protected int|string|array $columnSpan = 'full';

    protected function getColumns(): int
    {
        return 4;
    }

    protected function getStats(): array
    {
        $s = (new Reports($this->filters ?? []))->channelStats();

        return [
            Stat::make('E-posta gönderilen', $s['emailSent']),
            Stat::make('E-posta açılma', Reports::rate($s['emailOpened'], $s['emailSent']))
                ->description("{$s['emailOpened']} açıldı · tıklama ".Reports::rate($s['emailClicked'], $s['emailSent'])),
            Stat::make('WhatsApp gönderilen', $s['waSent']),
            Stat::make('WhatsApp → site açılma', Reports::rate($s['waViewed'], $s['waSent']))
                ->description("{$s['waViewed']} firma linki açtı"),
        ];
    }
}
