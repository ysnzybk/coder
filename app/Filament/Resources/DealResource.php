<?php

namespace App\Filament\Resources;

use App\Filament\Resources\DealResource\Pages;
use App\Filament\Resources\DealResource\RelationManagers\PaymentsRelationManager;
use App\Models\Deal;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class DealResource extends Resource
{
    protected static ?string $model = Deal::class;

    protected static ?string $navigationIcon = 'heroicon-o-banknotes';

    protected static ?string $navigationLabel = 'Satışlar & Ödemeler';

    protected static ?string $modelLabel = 'satış';

    protected static ?string $pluralModelLabel = 'Satışlar';

    protected static ?int $navigationSort = 4;

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\Placeholder::make('business')->label('Firma')->content(fn (?Deal $record) => $record?->business?->name),
            Forms\Components\TextInput::make('setup_price')->label('Kurulum (TL)')->numeric()->required(),
            Forms\Components\TextInput::make('maintenance_price')->label('Aylık bakım (TL)')->numeric()->required(),
            Forms\Components\DatePicker::make('started_at')->label('Başlangıç')->required(),
            Forms\Components\Select::make('status')->label('Durum')->options(Deal::STATUSES)->required()->live(),
            Forms\Components\DatePicker::make('cancelled_at')->label('İptal tarihi')
                ->visible(fn (Forms\Get $get) => $get('status') === 'cancelled'),
            Forms\Components\TextInput::make('custom_domain')->label('Firmanın alan adı')
                ->helperText('Alan adı bu sunucuya yönlendirildiğinde site bu adreste açılır (ör. firmaadi.com).')
                ->afterStateHydrated(fn ($component, ?Deal $record) => $component->state($record?->business?->site?->custom_domain))
                ->dehydrated(false)
                ->rule('nullable|regex:/^(?!-)[a-z0-9-]+(\\.[a-z0-9-]+)+$/i'),
            Forms\Components\Textarea::make('notes')->label('Not')->columnSpanFull(),
        ])->columns(2);
    }

    public static function table(Table $table): Table
    {
        return $table->defaultSort('started_at', 'desc')->columns([
            Tables\Columns\TextColumn::make('business.name')->label('Firma')->searchable()->weight('bold'),
            Tables\Columns\TextColumn::make('business.district.name')->label('İlçe'),
            Tables\Columns\TextColumn::make('setup_price')->label('Kurulum')->money('TRY', locale: 'tr'),
            Tables\Columns\TextColumn::make('maintenance_price')->label('Aylık')->money('TRY', locale: 'tr'),
            Tables\Columns\TextColumn::make('started_at')->label('Başlangıç')->date('d.m.Y')->sortable(),
            Tables\Columns\TextColumn::make('status')->label('Durum')->badge()
                ->formatStateUsing(fn ($s) => Deal::STATUSES[$s] ?? $s)
                ->color(fn ($s) => $s === 'active' ? 'success' : 'gray'),
            Tables\Columns\TextColumn::make('unpaid')->label('Bekleyen ödeme')
                ->getStateUsing(fn (Deal $r) => $r->payments()->whereNull('paid_at')->sum('amount'))
                ->money('TRY', locale: 'tr')->color(fn ($state) => $state > 0 ? 'danger' : null),
        ])->actions([Tables\Actions\EditAction::make()]);
    }

    public static function getRelations(): array
    {
        return [PaymentsRelationManager::class];
    }

    public static function canCreate(): bool
    {
        return false; // satış, Firmalar ekranındaki "Satış yapıldı" ile oluşturulur
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListDeals::route('/'),
            'edit' => Pages\EditDeal::route('/{record}/edit'),
        ];
    }
}
