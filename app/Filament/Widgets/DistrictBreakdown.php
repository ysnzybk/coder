<?php

namespace App\Filament\Widgets;

use App\Models\Business;
use App\Models\District;
use App\Support\Reports;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Widgets\Concerns\InteractsWithPageFilters;
use Filament\Widgets\TableWidget;
use Illuminate\Database\Eloquent\Builder;

class DistrictBreakdown extends TableWidget
{
    use InteractsWithPageFilters;

    protected static ?string $heading = 'İlçe kırılımı';

    protected static ?int $sort = 2;

    public function table(Table $table): Table
    {
        $scope = fn ($q) => $q->whereIn('id', (new Reports($this->filters ?? []))->businesses()->select('id'));

        return $table
            ->query(fn (): Builder => District::query()
                ->whereHas('businesses', $scope)
                ->withCount([
                    'businesses as target_count' => fn ($q) => $scope($q)->where('status', '!=', Business::STATUS_DISQUALIFIED),
                    'businesses as sent_count' => fn ($q) => $scope($q)->whereHas('outreachMessages', fn ($m) => $m->where('status', 'sent')),
                    'businesses as viewed_count' => fn ($q) => $scope($q)->whereHas('trackingEvents', fn ($m) => $m->where('type', 'site_view')->whereNotNull('outreach_message_id')),
                    'businesses as won_count' => fn ($q) => $scope($q)->whereHas('deal'),
                ])
                ->with('province'))
            ->defaultSort('target_count', 'desc')
            ->paginated([5, 10, 25])
            ->defaultPaginationPageOption(5)
            ->columns([
                Tables\Columns\TextColumn::make('name')->label('İlçe')->description(fn (District $d) => $d->province?->name),
                Tables\Columns\TextColumn::make('target_count')->label('Hedef')->sortable(),
                Tables\Columns\TextColumn::make('sent_count')->label('Gönderilen')->sortable(),
                Tables\Columns\TextColumn::make('viewed_count')->label('Açılan')->sortable(),
                Tables\Columns\TextColumn::make('won_count')->label('Satış')->sortable(),
            ]);
    }
}
