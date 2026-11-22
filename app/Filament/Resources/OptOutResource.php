<?php

namespace App\Filament\Resources;

use App\Filament\Resources\OptOutResource\Pages;
use App\Models\OptOut;
use App\Services\Phone\TurkishPhone;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class OptOutResource extends Resource
{
    protected static ?string $model = OptOut::class;

    protected static ?string $navigationIcon = 'heroicon-o-no-symbol';

    protected static ?string $navigationGroup = 'Ayarlar';

    protected static ?string $navigationLabel = 'Ret (kara liste)';

    protected static ?string $modelLabel = 'ret kaydı';

    protected static ?string $pluralModelLabel = 'Ret listesi';

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\Select::make('type')->label('Tür')->options(['phone' => 'Telefon', 'email' => 'E-posta'])->required(),
            Forms\Components\TextInput::make('value')->label('Değer')->required()
                ->dehydrateStateUsing(fn ($state, Forms\Get $get) => $get('type') === 'phone' ? (TurkishPhone::normalize($state) ?? $state) : mb_strtolower(trim($state))),
            Forms\Components\TextInput::make('source')->label('Kaynak')->default('manuel'),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table->defaultSort('id', 'desc')->columns([
            Tables\Columns\TextColumn::make('value')->label('Değer')->searchable(),
            Tables\Columns\TextColumn::make('type')->label('Tür'),
            Tables\Columns\TextColumn::make('source')->label('Kaynak'),
            Tables\Columns\TextColumn::make('created_at')->label('Tarih')->dateTime('d.m.Y H:i'),
        ])->actions([Tables\Actions\DeleteAction::make()]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListOptOuts::route('/'),
            'create' => Pages\CreateOptOut::route('/create'),
        ];
    }
}
