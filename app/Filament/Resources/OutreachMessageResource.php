<?php

namespace App\Filament\Resources;

use App\Filament\Resources\OutreachMessageResource\Pages;
use App\Models\Business;
use App\Models\OutreachMessage;
use App\Models\TrackingEvent;
use App\Services\Outreach\OutreachService;
use App\Services\Phone\TurkishPhone;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;

class OutreachMessageResource extends Resource
{
    protected static ?string $model = OutreachMessage::class;

    protected static ?string $navigationIcon = 'heroicon-o-paper-airplane';

    protected static ?string $navigationLabel = 'Onay & Gönderim';

    protected static ?string $modelLabel = 'mesaj';

    protected static ?string $pluralModelLabel = 'Mesajlar';

    protected static ?int $navigationSort = 3;

    public static function getNavigationBadge(): ?string
    {
        $n = OutreachMessage::where('status', OutreachMessage::STATUS_DRAFT)->count();

        return $n ? (string) $n : null;
    }

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\Grid::make(['default' => 1, 'lg' => 2])->schema([
                Forms\Components\Group::make([
                    Forms\Components\Placeholder::make('business')->label('Firma')
                        ->content(fn (?OutreachMessage $record) => $record?->business?->name.' — '.($record?->business?->district?->name ?? '')),
                    Forms\Components\Placeholder::make('to')->label('Alıcı')
                        ->content(fn (?OutreachMessage $record) => (Business::CHANNELS[$record?->channel] ?? '').': '.($record?->channel === 'email' ? $record?->recipient : TurkishPhone::display($record?->recipient))),
                    Forms\Components\TextInput::make('subject')->label('Konu')
                        ->visible(fn (?OutreachMessage $record) => $record?->channel === Business::CHANNEL_EMAIL),
                    Forms\Components\Textarea::make('body')->label('Mesaj')->rows(12)->required()
                        ->helperText('Linkler takip kodu içerir; silmeyin.'),
                ]),
                Forms\Components\ViewField::make('preview')
                    ->label('Site önizleme')
                    ->view('filament.site-preview')
                    ->dehydrated(false),
            ]),
        ])->disabled(fn (?OutreachMessage $record) => $record && $record->status !== OutreachMessage::STATUS_DRAFT);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('id', 'desc')
            ->columns([
                Tables\Columns\TextColumn::make('business.name')->label('Firma')->searchable()->weight('bold')
                    ->description(fn (OutreachMessage $r) => $r->business?->district?->name),
                Tables\Columns\TextColumn::make('channel')->label('Kanal')->badge()
                    ->formatStateUsing(fn ($state) => Business::CHANNELS[$state] ?? $state)
                    ->color(fn ($state) => $state === 'whatsapp' ? 'success' : 'info'),
                Tables\Columns\TextColumn::make('recipient')->label('Alıcı')
                    ->formatStateUsing(fn ($state, OutreachMessage $r) => $r->channel === 'email' ? $state : TurkishPhone::display($state)),
                Tables\Columns\TextColumn::make('body')->label('Mesaj')->limit(70)->wrap()->toggleable(),
                Tables\Columns\TextColumn::make('status')->label('Durum')->badge()
                    ->formatStateUsing(fn ($state) => OutreachMessage::STATUSES[$state] ?? $state)
                    ->color(fn ($state) => match ($state) {
                        'sent' => 'success', 'approved', 'queued' => 'info', 'draft' => 'warning', default => 'danger'
                    }),
                Tables\Columns\TextColumn::make('sent_at')->label('Gönderim')->dateTime('d.m.Y H:i')->placeholder('—')->sortable(),
                Tables\Columns\IconColumn::make('first_opened_at')->label('Açıldı')->boolean()->getStateUsing(fn (OutreachMessage $r) => (bool) $r->first_opened_at)->toggleable(),
                Tables\Columns\IconColumn::make('viewed')->label('Site açıldı')->boolean()
                    ->getStateUsing(fn (OutreachMessage $r) => TrackingEvent::where('outreach_message_id', $r->id)->where('type', 'site_view')->exists()),
                Tables\Columns\TextColumn::make('error')->label('Hata')->limit(30)->color('danger')->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('channel')->label('Kanal')->options(Business::CHANNELS),
                Tables\Filters\SelectFilter::make('status')->label('Durum')->options(OutreachMessage::STATUSES),
            ])
            ->actions([
                Tables\Actions\Action::make('site')->label('Site')->icon('heroicon-o-eye')->color('gray')
                    ->url(fn (OutreachMessage $r) => $r->business?->site?->url(), shouldOpenInNewTab: true)
                    ->visible(fn (OutreachMessage $r) => $r->business?->site !== null),
                Tables\Actions\EditAction::make()->label(fn (OutreachMessage $r) => $r->status === 'draft' ? 'İncele / Düzenle' : 'Görüntüle'),
                Tables\Actions\Action::make('approve')->label('Onayla')->icon('heroicon-o-check')->color('success')
                    ->visible(fn (OutreachMessage $r) => $r->status === OutreachMessage::STATUS_DRAFT)
                    ->action(function (OutreachMessage $r) {
                        app(OutreachService::class)->approve($r);
                        Notification::make()->title($r->channel === 'email' ? 'Onaylandı — e-posta kuyruğa alındı' : 'Onaylandı — "WhatsApp\'ta aç" ile gönderin')->success()->send();
                    }),
                Tables\Actions\Action::make('reject')->label('Reddet')->icon('heroicon-o-x-mark')->color('danger')
                    ->requiresConfirmation()
                    ->visible(fn (OutreachMessage $r) => in_array($r->status, ['draft', 'approved', 'queued'], true))
                    ->action(fn (OutreachMessage $r) => app(OutreachService::class)->reject($r)),
                Tables\Actions\Action::make('whatsapp')->label('WhatsApp\'ta aç')->icon('heroicon-o-chat-bubble-oval-left')->color('success')
                    ->visible(fn (OutreachMessage $r) => $r->channel === 'whatsapp' && in_array($r->status, ['approved', 'sent'], true))
                    ->url(fn (OutreachMessage $r) => route('admin.whatsapp', $r), shouldOpenInNewTab: true),
            ])
            ->bulkActions([
                Tables\Actions\BulkAction::make('approve')->label('Toplu onayla')->icon('heroicon-o-check')->color('success')
                    ->requiresConfirmation()
                    ->modalDescription('Seçili taslaklar onaylanır. E-postalar SMTP limitine göre sırayla gönderilir; WhatsApp mesajları "WhatsApp\'ta aç" ile tek tek gönderilir.')
                    ->action(function (Collection $records) {
                        $svc = app(OutreachService::class);
                        $n = 0;
                        foreach ($records as $r) {
                            if ($r->status === OutreachMessage::STATUS_DRAFT) {
                                $svc->approve($r);
                                $n++;
                            }
                        }
                        Notification::make()->title("{$n} mesaj onaylandı")->success()->send();
                    })
                    ->deselectRecordsAfterCompletion(),
                Tables\Actions\BulkAction::make('reject')->label('Toplu reddet')->icon('heroicon-o-x-mark')->color('danger')
                    ->requiresConfirmation()
                    ->action(fn (Collection $records) => $records->each(fn ($r) => app(OutreachService::class)->reject($r)))
                    ->deselectRecordsAfterCompletion(),
            ]);
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->with(['business.site', 'business.district']);
    }

    public static function canCreate(): bool
    {
        return false;
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListOutreachMessages::route('/'),
            'edit' => Pages\EditOutreachMessage::route('/{record}/edit'),
        ];
    }
}
