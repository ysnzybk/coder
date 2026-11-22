<?php

namespace App\Filament\Resources;

use App\Filament\Resources\SearchJobResource\Pages;
use App\Jobs\RunSearchJob;
use App\Models\Category;
use App\Models\District;
use App\Models\Province;
use App\Models\SearchJob;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Forms\Get;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class SearchJobResource extends Resource
{
    protected static ?string $model = SearchJob::class;

    protected static ?string $navigationIcon = 'heroicon-o-magnifying-glass';

    protected static ?string $navigationLabel = 'Firma Ara';

    protected static ?string $modelLabel = 'arama';

    protected static ?string $pluralModelLabel = 'Aramalar';

    protected static ?int $navigationSort = 1;

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\Placeholder::make('api_warning')
                ->hiddenLabel()
                ->content('⚠️ GOOGLE_PLACES_API_KEY .env dosyasında tanımlı değil — arama çalışmaz.')
                ->visible(fn () => blank(config('leadgen.places.key'))),
            Forms\Components\Select::make('province_id')
                ->label('İl')
                ->options(Province::orderBy('name')->pluck('name', 'id'))
                ->searchable()
                ->required()
                ->live()
                ->afterStateUpdated(fn (Forms\Set $set) => $set('district_ids', [])),
            Forms\Components\Select::make('district_ids')
                ->label('İlçe(ler)')
                ->multiple()
                ->searchable()
                ->required()
                ->options(fn (Get $get) => District::where('province_id', $get('province_id'))->orderBy('name')->pluck('name', 'id'))
                ->helperText('Her ilçe için en fazla 60 sonuç (3 sayfa) taranır. Her sayfa 1 Places API çağrısıdır.'),
            Forms\Components\Select::make('category_id')
                ->label('Sektör')
                ->options(Category::where('is_active', true)->orderBy('name')->pluck('name', 'id'))
                ->searchable()
                ->required(),
        ])->columns(1);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('id', 'desc')
            ->poll('10s')
            ->columns([
                Tables\Columns\TextColumn::make('created_at')->label('Tarih')->dateTime('d.m.Y H:i')->sortable(),
                Tables\Columns\TextColumn::make('province.name')->label('İl'),
                Tables\Columns\TextColumn::make('district_ids')->label('İlçeler')
                    ->formatStateUsing(fn (SearchJob $record) => $record->districts()->pluck('name')->implode(', '))
                    ->wrap()->limit(60),
                Tables\Columns\TextColumn::make('category.name')->label('Sektör'),
                Tables\Columns\TextColumn::make('status')->label('Durum')->badge()
                    ->formatStateUsing(fn ($state) => SearchJob::STATUSES[$state] ?? $state)
                    ->color(fn ($state) => match ($state) {
                        'completed' => 'success', 'failed' => 'danger', 'running' => 'warning', default => 'gray'
                    }),
                Tables\Columns\TextColumn::make('found_count')->label('Bulunan')->numeric(),
                Tables\Columns\TextColumn::make('qualified_count')->label('Sitesi yok (hedef)')->numeric(),
                Tables\Columns\TextColumn::make('api_calls')->label('API çağrısı')->numeric(),
                Tables\Columns\TextColumn::make('error')->label('Hata')->limit(40)->tooltip(fn ($state) => $state)->color('danger'),
            ])
            ->actions([
                Tables\Actions\Action::make('businesses')
                    ->label('Firmalar')
                    ->icon('heroicon-o-building-storefront')
                    ->url(fn (SearchJob $record) => BusinessResource::getUrl('index', ['tableFilters[search_job_id][value]' => $record->id])),
                Tables\Actions\Action::make('retry')
                    ->label('Tekrar çalıştır')
                    ->icon('heroicon-o-arrow-path')
                    ->visible(fn (SearchJob $record) => $record->status === 'failed')
                    ->action(fn (SearchJob $record) => RunSearchJob::dispatch($record->id)),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListSearchJobs::route('/'),
            'create' => Pages\CreateSearchJob::route('/create'),
        ];
    }
}
