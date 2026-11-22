<?php

namespace App\Filament\Resources\BusinessResource\Pages;

use App\Filament\Resources\BusinessResource;
use App\Models\Business;
use Filament\Resources\Components\Tab;
use Filament\Resources\Pages\ListRecords;
use Illuminate\Database\Eloquent\Builder;

class ListBusinesses extends ListRecords
{
    protected static string $resource = BusinessResource::class;

    public function getTabs(): array
    {
        $count = fn (array $statuses) => Business::whereIn('status', $statuses)->count();

        $active = [Business::STATUS_DISCOVERED, Business::STATUS_GENERATING, Business::STATUS_SITE_READY, Business::STATUS_APPROVED, Business::STATUS_FAILED];
        $sent = [Business::STATUS_SENT, Business::STATUS_VIEWED, Business::STATUS_REPLIED];

        return [
            'active' => Tab::make('Hedefler')
                ->badge($count($active))
                ->modifyQueryUsing(fn (Builder $query) => $query->whereIn('status', $active)),
            'manual' => Tab::make('Manuel iletişim')
                ->badge(Business::where('contact_channel', Business::CHANNEL_MANUAL)->whereNotIn('status', [Business::STATUS_WON, Business::STATUS_LOST, Business::STATUS_OPTED_OUT])->count())
                ->modifyQueryUsing(fn (Builder $query) => $query->where('contact_channel', Business::CHANNEL_MANUAL)->whereNotIn('status', [Business::STATUS_WON, Business::STATUS_LOST, Business::STATUS_OPTED_OUT])),
            'sent' => Tab::make('Gönderildi / Görüşülüyor')
                ->badge($count($sent))
                ->modifyQueryUsing(fn (Builder $query) => $query->whereIn('status', $sent)),
            'won' => Tab::make('Satış')
                ->badge($count([Business::STATUS_WON]))
                ->modifyQueryUsing(fn (Builder $query) => $query->where('status', Business::STATUS_WON)),
            'closed' => Tab::make('Kaybedilen / Ret')
                ->modifyQueryUsing(fn (Builder $query) => $query->whereIn('status', [Business::STATUS_LOST, Business::STATUS_OPTED_OUT])),
            'disqualified' => Tab::make('Elenen')
                ->modifyQueryUsing(fn (Builder $query) => $query->where('status', Business::STATUS_DISQUALIFIED)),
            'all' => Tab::make('Tümü'),
        ];
    }
}
