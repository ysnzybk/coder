<?php

namespace App\Filament\Resources;

use App\Filament\Resources\MessageTemplateResource\Pages;
use App\Models\Business;
use App\Models\MessageTemplate;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Support\HtmlString;

class MessageTemplateResource extends Resource
{
    protected static ?string $model = MessageTemplate::class;

    protected static ?string $navigationIcon = 'heroicon-o-document-text';

    protected static ?string $navigationGroup = 'Ayarlar';

    protected static ?string $navigationLabel = 'Mesaj şablonları';

    protected static ?string $modelLabel = 'şablon';

    protected static ?string $pluralModelLabel = 'Mesaj şablonları';

    public static function form(Form $form): Form
    {
        $vars = collect(MessageTemplate::VARIABLES)->map(fn ($d, $k) => "{$k} → {$d}")->implode("\n");

        return $form->schema([
            Forms\Components\TextInput::make('name')->label('Ad')->required(),
            Forms\Components\Select::make('channel')->label('Kanal')->options([
                Business::CHANNEL_WHATSAPP => 'WhatsApp', Business::CHANNEL_EMAIL => 'E-posta',
            ])->required()->live(),
            Forms\Components\TextInput::make('subject')->label('E-posta konusu')
                ->visible(fn (Forms\Get $get) => $get('channel') === Business::CHANNEL_EMAIL),
            Forms\Components\Textarea::make('body')->label('Mesaj')->rows(10)->required()->columnSpanFull()
                ->helperText(new HtmlString('<pre style="white-space:pre-wrap;font-size:12px">Kullanılabilir değişkenler:'."\n".e($vars).'</pre>')),
            Forms\Components\Toggle::make('is_default')->label('Bu kanal için varsayılan'),
        ])->columns(2);
    }

    public static function table(Table $table): Table
    {
        return $table->columns([
            Tables\Columns\TextColumn::make('name')->label('Ad'),
            Tables\Columns\TextColumn::make('channel')->label('Kanal')->badge(),
            Tables\Columns\IconColumn::make('is_default')->label('Varsayılan')->boolean(),
            Tables\Columns\TextColumn::make('body')->label('Mesaj')->limit(80),
        ])->actions([Tables\Actions\EditAction::make(), Tables\Actions\DeleteAction::make()]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListMessageTemplates::route('/'),
            'create' => Pages\CreateMessageTemplate::route('/create'),
            'edit' => Pages\EditMessageTemplate::route('/{record}/edit'),
        ];
    }
}
