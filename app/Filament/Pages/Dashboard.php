<?php

namespace App\Filament\Pages;

use App\Models\Category;
use App\Models\District;
use App\Models\Province;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Pages\Dashboard as BaseDashboard;
use Filament\Pages\Dashboard\Concerns\HasFiltersForm;

class Dashboard extends BaseDashboard
{
    use HasFiltersForm;

    protected static ?string $title = 'Rapor';

    public function filtersForm(Form $form): Form
    {
        return $form->schema([
            Forms\Components\Section::make()->columns(['default' => 1, 'md' => 5])->schema([
                Forms\Components\Select::make('province_id')->label('İl')->searchable()->live()
                    ->options(fn () => Province::whereHas('businesses')->orderBy('name')->pluck('name', 'id'))
                    ->afterStateUpdated(fn (Forms\Set $set) => $set('district_ids', [])),
                Forms\Components\Select::make('district_ids')->label('İlçe')->multiple()->searchable()
                    ->options(fn (Forms\Get $get) => District::whereHas('businesses')
                        ->when($get('province_id'), fn ($q, $v) => $q->where('province_id', $v))
                        ->orderBy('name')->pluck('name', 'id')),
                Forms\Components\Select::make('category_id')->label('Sektör')
                    ->options(fn () => Category::orderBy('name')->pluck('name', 'id')),
                Forms\Components\DatePicker::make('from')->label('Bulunma (başlangıç)'),
                Forms\Components\DatePicker::make('until')->label('Bulunma (bitiş)'),
            ]),
        ]);
    }

    public function getColumns(): int|string|array
    {
        return 2;
    }
}
