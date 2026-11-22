<?php

namespace App\Filament\Resources;

use App\Filament\Resources\CategoryResource\Pages;
use App\Models\Category;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class CategoryResource extends Resource
{
    protected static ?string $model = Category::class;

    protected static ?string $navigationIcon = 'heroicon-o-tag';

    protected static ?string $navigationGroup = 'Ayarlar';

    protected static ?string $navigationLabel = 'Sektörler';

    protected static ?string $modelLabel = 'sektör';

    protected static ?string $pluralModelLabel = 'Sektörler';

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\TextInput::make('name')->label('Ad')->required(),
            Forms\Components\TextInput::make('slug')->required()->unique(ignoreRecord: true),
            Forms\Components\TextInput::make('search_term')->label('Google arama terimi')->required()
                ->helperText('Sorgu: "{terim} {ilçe} {il}" — ör. "pet shop"'),
            Forms\Components\TextInput::make('included_type')->label('Places türü (opsiyonel)')
                ->helperText('Google Places "Table A" türü, ör. pet_store, restaurant. Emin değilseniz boş bırakın.'),
            Forms\Components\Select::make('template')->label('Site şablonu')->options(Category::TEMPLATES)->required(),
            Forms\Components\TextInput::make('setup_price')->label('Kurulum fiyatı (boş = varsayılan)')->numeric(),
            Forms\Components\TextInput::make('maintenance_price')->label('Bakım fiyatı (boş = varsayılan)')->numeric(),
            Forms\Components\Toggle::make('is_active')->label('Aktif')->default(true),
        ])->columns(2);
    }

    public static function table(Table $table): Table
    {
        return $table->columns([
            Tables\Columns\TextColumn::make('name')->label('Ad')->searchable(),
            Tables\Columns\TextColumn::make('search_term')->label('Arama terimi'),
            Tables\Columns\TextColumn::make('included_type')->label('Places türü')->placeholder('—'),
            Tables\Columns\TextColumn::make('template')->label('Şablon')->formatStateUsing(fn ($state) => Category::TEMPLATES[$state] ?? $state),
            Tables\Columns\TextColumn::make('setup_price')->label('Kurulum')->placeholder('varsayılan'),
            Tables\Columns\ToggleColumn::make('is_active')->label('Aktif'),
        ])->actions([Tables\Actions\EditAction::make()]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListCategories::route('/'),
            'create' => Pages\CreateCategory::route('/create'),
            'edit' => Pages\EditCategory::route('/{record}/edit'),
        ];
    }
}
