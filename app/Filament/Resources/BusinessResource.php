<?php

namespace App\Filament\Resources;

use App\Filament\Resources\BusinessResource\Pages;
use App\Jobs\GenerateSiteImagesJob;
use App\Jobs\GenerateSiteJob;
use App\Models\Business;
use App\Models\Category;
use App\Models\District;
use App\Models\Province;
use App\Services\Outreach\MessageRenderer;
use App\Services\Outreach\OutreachService;
use App\Services\Phone\TurkishPhone;
use App\Services\Places\BusinessImporter;
use App\Support\CsvExport;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Str;

class BusinessResource extends Resource
{
    protected static ?string $model = Business::class;

    protected static ?string $navigationIcon = 'heroicon-o-building-storefront';

    protected static ?string $navigationLabel = 'Firmalar';

    protected static ?string $modelLabel = 'firma';

    protected static ?string $pluralModelLabel = 'Firmalar';

    protected static ?string $recordTitleAttribute = 'name';

    protected static ?int $navigationSort = 2;

    public static function statusColor(string $status): string
    {
        return match ($status) {
            Business::STATUS_WON => 'success',
            Business::STATUS_SITE_READY, Business::STATUS_APPROVED => 'info',
            Business::STATUS_SENT, Business::STATUS_VIEWED, Business::STATUS_REPLIED => 'warning',
            Business::STATUS_FAILED, Business::STATUS_LOST, Business::STATUS_OPTED_OUT => 'danger',
            Business::STATUS_MANUAL_CONTACT => 'primary',
            Business::STATUS_NEEDS_VERIFICATION => 'warning',
            default => 'gray',
        };
    }

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\Section::make('Firma')->columns(2)->schema([
                Forms\Components\TextInput::make('name')->label('Ad')->required(),
                Forms\Components\TextInput::make('phone')->label('Telefon')->tel()
                    ->dehydrateStateUsing(fn ($state) => TurkishPhone::normalize($state) ?? $state),
                Forms\Components\TextInput::make('email')->label('E-posta')->email()
                    ->helperText('Google e-posta vermez; biliyorsanız buraya girin.'),
                Forms\Components\Select::make('contact_channel')->label('İletişim kanalı')->options(Business::CHANNELS),
                Forms\Components\Textarea::make('address')->label('Adres')->rows(2)->columnSpanFull(),
                Forms\Components\Select::make('status')->label('Durum')->options(Business::STATUSES)->required(),
                Forms\Components\Select::make('category_id')->label('Sektör')->relationship('category', 'name'),
            ]),
            Forms\Components\Section::make('Fiyat (boşsa sektör/varsayılan fiyat)')->columns(2)->schema([
                Forms\Components\TextInput::make('setup_price')->label('Kurulum (TL)')->numeric(),
                Forms\Components\TextInput::make('maintenance_price')->label('Aylık bakım (TL)')->numeric(),
            ]),
            Forms\Components\Section::make('Arama / not')->columns(2)->schema([
                Forms\Components\Select::make('call_outcome')->label('Arama sonucu')->options(Business::CALL_OUTCOMES),
                Forms\Components\Textarea::make('call_note')->label('Arama notu')->rows(2),
                Forms\Components\Textarea::make('notes')->label('Notlar')->rows(3)->columnSpanFull(),
            ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('id', 'desc')
            ->columns([
                Tables\Columns\TextColumn::make('name')->label('Firma')->searchable()->sortable()->weight('bold')
                    ->description(fn (Business $r) => $r->address ? Str::limit($r->address, 34) : null),
                Tables\Columns\TextColumn::make('district.name')->label('İlçe')->sortable()
                    ->description(fn (Business $r) => $r->province?->name),
                Tables\Columns\TextColumn::make('category.name')->label('Sektör')->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('phone')->label('Telefon')
                    ->formatStateUsing(fn ($state) => TurkishPhone::display($state))
                    ->description(fn (Business $r) => match ($r->phone_type) {
                        'mobile' => 'Cep', 'landline' => 'Sabit', default => null
                    }),
                Tables\Columns\TextColumn::make('email')->label('E-posta')->toggleable(isToggledHiddenByDefault: true)->placeholder('—'),
                Tables\Columns\TextColumn::make('contact_channel')->label('Kanal')->badge()
                    ->formatStateUsing(fn ($state) => Business::CHANNELS[$state] ?? '—')
                    ->color(fn ($state) => match ($state) {
                        'whatsapp' => 'success', 'email' => 'info', default => 'gray'
                    }),
                Tables\Columns\TextColumn::make('website_status')->label('Web')->badge()->toggleable(isToggledHiddenByDefault: true)
                    ->formatStateUsing(fn ($state) => ['none' => 'Yok', 'social_only' => 'Sadece sosyal', 'real' => 'Var'][$state] ?? $state)
                    ->color(fn ($state) => $state === 'real' ? 'danger' : 'gray'),
                Tables\Columns\TextColumn::make('rating')->label('Puan')->sortable()->toggleable()
                    ->formatStateUsing(fn ($state, Business $r) => $state ? "★ {$state} ({$r->review_count})" : '—'),
                Tables\Columns\TextColumn::make('status')->label('Durum')->badge()
                    ->formatStateUsing(fn ($state) => Business::STATUSES[$state] ?? $state)
                    ->color(fn ($state) => self::statusColor($state))
                    ->description(fn (Business $r) => $r->status === Business::STATUS_DISQUALIFIED ? $r->disqualify_reason : ($r->call_outcome ? Business::CALL_OUTCOMES[$r->call_outcome] : null)),
                Tables\Columns\TextColumn::make('last_error')->label('Hata')->limit(30)->tooltip(fn ($state) => $state)
                    ->color('danger')->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('created_at')->label('Bulunma')->date('d.m.Y')->sortable()->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('province_id')->label('İl')->options(fn () => Province::whereHas('businesses')->orderBy('name')->pluck('name', 'id'))->searchable(),
                Tables\Filters\SelectFilter::make('district_id')->label('İlçe')->options(fn () => District::whereHas('businesses')->orderBy('name')->pluck('name', 'id'))->searchable()->multiple(),
                Tables\Filters\SelectFilter::make('category_id')->label('Sektör')->options(fn () => Category::orderBy('name')->pluck('name', 'id')),
                Tables\Filters\SelectFilter::make('status')->label('Durum')->options(Business::STATUSES)->multiple(),
                Tables\Filters\SelectFilter::make('contact_channel')->label('Kanal')->options(Business::CHANNELS),
                Tables\Filters\SelectFilter::make('source')->label('Kaynak')->options(['google' => 'Google', 'file' => 'Dosya (Overture)', 'manual' => 'Manuel']),
                Tables\Filters\SelectFilter::make('website_status')->label('Web')->options(['none' => 'Yok', 'social_only' => 'Sadece sosyal', 'real' => 'Var']),
                Tables\Filters\SelectFilter::make('search_job_id')->label('Arama #')->relationship('searchJob', 'id'),
            ])
            ->actions([
                Tables\Actions\Action::make('preview')
                    ->label('Site')
                    ->icon('heroicon-o-eye')
                    ->color('gray')
                    ->url(fn (Business $r) => $r->site?->url(), shouldOpenInNewTab: true)
                    ->visible(fn (Business $r) => $r->site !== null),
                Tables\Actions\Action::make('webSearch')
                    ->label('Web\'de ara')
                    ->icon('heroicon-o-magnifying-glass')
                    ->iconButton()
                    ->tooltip('Web\'de ara (Google)')
                    ->color('warning')
                    ->url(fn (Business $r) => 'https://www.google.com/search?q='.urlencode($r->name.' '.($r->district?->name ?? '').' '.($r->province?->name ?? '')), shouldOpenInNewTab: true)
                    ->visible(fn (Business $r) => $r->status === Business::STATUS_NEEDS_VERIFICATION),
                Tables\Actions\Action::make('confirmNoSite')
                    ->label('Sitesi yok → onayla')
                    ->icon('heroicon-o-check-circle')
                    ->iconButton()
                    ->tooltip('Sitesi yok → onayla (site üretimini başlatır)')
                    ->color('success')
                    ->visible(fn (Business $r) => $r->status === Business::STATUS_NEEDS_VERIFICATION)
                    ->action(function (Business $r) {
                        app(BusinessImporter::class)->confirmNoWebsite($r);
                        Notification::make()->title('Doğrulandı — normal akışa alındı')->success()->send();
                    }),
                Tables\Actions\Action::make('rejectHasSite')
                    ->label('Sitesi var → ele')
                    ->icon('heroicon-o-x-circle')
                    ->iconButton()
                    ->tooltip('Sitesi var → ele')
                    ->color('danger')
                    ->requiresConfirmation()
                    ->visible(fn (Business $r) => $r->status === Business::STATUS_NEEDS_VERIFICATION)
                    ->action(fn (Business $r) => app(BusinessImporter::class)->rejectHasWebsite($r)),
                Tables\Actions\ActionGroup::make([
                    self::generateSiteAction(),
                    self::editContentAction(),
                    self::skipImagesAction(),
                    self::addEmailAction(),
                    self::callOutcomeAction(),
                    Tables\Actions\Action::make('replied')
                        ->label('Cevap geldi')
                        ->icon('heroicon-o-chat-bubble-left-right')
                        ->visible(fn (Business $r) => in_array($r->status, [Business::STATUS_SENT, Business::STATUS_VIEWED], true))
                        ->action(fn (Business $r) => $r->update(['status' => Business::STATUS_REPLIED])),
                    self::markWonAction(),
                    Tables\Actions\Action::make('lost')
                        ->label('Kaybedildi')
                        ->icon('heroicon-o-x-circle')
                        ->color('danger')
                        ->requiresConfirmation()
                        ->visible(fn (Business $r) => ! in_array($r->status, [Business::STATUS_WON, Business::STATUS_LOST, Business::STATUS_DISQUALIFIED], true))
                        ->action(fn (Business $r) => $r->update(['status' => Business::STATUS_LOST])),
                    Tables\Actions\Action::make('maps')
                        ->label('Google Haritalar')
                        ->icon('heroicon-o-map-pin')
                        ->url(fn (Business $r) => $r->maps_url, shouldOpenInNewTab: true)
                        ->visible(fn (Business $r) => filled($r->maps_url)),
                    Tables\Actions\EditAction::make(),
                ]),
            ])
            ->bulkActions([
                Tables\Actions\BulkAction::make('generate')
                    ->label('Site üret')
                    ->icon('heroicon-o-sparkles')
                    ->requiresConfirmation()
                    ->modalDescription('Seçili firmalar için AI içerik ve görsel üretilecek (API kotası harcar). Elenen/ret verenler atlanır.')
                    ->action(function (Collection $records) {
                        $n = 0;
                        foreach ($records as $r) {
                            if (self::canGenerate($r)) {
                                GenerateSiteJob::dispatch($r->id);
                                $n++;
                            }
                        }
                        Notification::make()->title("{$n} firma için site üretimi kuyruğa alındı")->success()->send();
                    })
                    ->deselectRecordsAfterCompletion(),
                Tables\Actions\BulkAction::make('confirmNoSite')
                    ->label('Sitesi yok → onayla')
                    ->icon('heroicon-o-check-circle')
                    ->color('success')
                    ->requiresConfirmation()
                    ->modalDescription('Seçili "Doğrulama bekliyor" firmaları için web sitesi olmadığını onaylıyorsunuz; site üretimi başlar (AI/görsel kotası harcar).')
                    ->action(function (Collection $records) {
                        $n = 0;
                        foreach ($records as $r) {
                            if ($r->status === Business::STATUS_NEEDS_VERIFICATION) {
                                app(BusinessImporter::class)->confirmNoWebsite($r);
                                $n++;
                            }
                        }
                        Notification::make()->title("{$n} firma doğrulandı")->success()->send();
                    })
                    ->deselectRecordsAfterCompletion(),
                Tables\Actions\BulkAction::make('csv')
                    ->label('CSV dışa aktar')
                    ->icon('heroicon-o-arrow-down-tray')
                    ->action(fn (Collection $records) => CsvExport::businesses($records)),
            ]);
    }

    public static function canGenerate(Business $r): bool
    {
        return ! in_array($r->status, [
            Business::STATUS_DISQUALIFIED, Business::STATUS_OPTED_OUT, Business::STATUS_GENERATING, Business::STATUS_WON, Business::STATUS_NEEDS_VERIFICATION,
        ], true);
    }

    private static function generateSiteAction(): Tables\Actions\Action
    {
        return Tables\Actions\Action::make('generate')
            ->label(fn (Business $r) => $r->site ? 'Siteyi yeniden üret' : 'Site üret')
            ->icon('heroicon-o-sparkles')
            ->color('primary')
            ->requiresConfirmation()
            ->modalDescription('AI içerik + görsel yeniden üretilir (ücretsiz kota harcar). Elle yaptığınız içerik düzenlemeleri silinir.')
            ->visible(fn (Business $r) => self::canGenerate($r))
            ->action(function (Business $r) {
                GenerateSiteJob::dispatch($r->id);
                Notification::make()->title('Site üretimi kuyruğa alındı')->success()->send();
            });
    }

    private static function editContentAction(): Tables\Actions\Action
    {
        return Tables\Actions\Action::make('editContent')
            ->label('İçeriği düzenle')
            ->icon('heroicon-o-pencil-square')
            ->visible(fn (Business $r) => $r->site !== null)
            ->modalWidth('3xl')
            ->fillForm(fn (Business $r) => collect($r->site->content ?? [])->only(['hero_title', 'hero_subtitle', 'tagline', 'about', 'review_summary', 'cta_text', 'services'])->all())
            ->form([
                Forms\Components\TextInput::make('hero_title')->label('Ana başlık')->required()->maxLength(80),
                Forms\Components\TextInput::make('hero_subtitle')->label('Alt başlık')->maxLength(170),
                Forms\Components\TextInput::make('tagline')->label('Üst etiket')->maxLength(80),
                Forms\Components\Textarea::make('about')->label('Hakkımızda')->rows(4)->maxLength(700),
                Forms\Components\Textarea::make('review_summary')->label('Yorum özeti')->rows(2)->maxLength(320),
                Forms\Components\TextInput::make('cta_text')->label('Buton metni')->maxLength(40),
                Forms\Components\Repeater::make('services')->label('Hizmetler / ürünler')->minItems(1)->maxItems(8)->schema([
                    Forms\Components\TextInput::make('name')->label('Ad')->required()->maxLength(50),
                    Forms\Components\TextInput::make('description')->label('Açıklama')->required()->maxLength(200),
                ])->columns(2)->collapsible(),
            ])
            ->action(function (Business $r, array $data) {
                $r->site->update(['content' => array_merge($r->site->content ?? [], $data, ['_edited' => true])]);
                Notification::make()->title('İçerik güncellendi')->success()->send();
            });
    }

    private static function skipImagesAction(): Tables\Actions\Action
    {
        return Tables\Actions\Action::make('skipImages')
            ->label('Görselsiz hazırla')
            ->icon('heroicon-o-photo')
            ->requiresConfirmation()
            ->modalDescription('Cloudflare günlük ücretsiz kotasını beklemeden site mevcut görsellerle hazır işaretlenir.')
            ->visible(fn (Business $r) => $r->status === Business::STATUS_GENERATING && $r->site !== null)
            ->action(function (Business $r) {
                GenerateSiteImagesJob::dispatch($r->site->id, true);
                Notification::make()->title('Site görselsiz hazırlanıyor')->success()->send();
            });
    }

    private static function addEmailAction(): Tables\Actions\Action
    {
        return Tables\Actions\Action::make('email')
            ->label('E-posta ekle')
            ->icon('heroicon-o-envelope')
            ->visible(fn (Business $r) => ! in_array($r->status, [Business::STATUS_OPTED_OUT, Business::STATUS_DISQUALIFIED, Business::STATUS_WON], true))
            ->fillForm(fn (Business $r) => ['email' => $r->email])
            ->form([Forms\Components\TextInput::make('email')->label('E-posta')->email()->required()])
            ->action(function (Business $r, array $data) {
                $r->update(['email' => mb_strtolower(trim($data['email']))]);

                // Doğrulama bekleyen firmada yalnızca kanalı güncelle; durumu (ve site üretimini) doğrulama belirler.
                if ($r->status === Business::STATUS_NEEDS_VERIFICATION) {
                    $r->update(['contact_channel' => $r->phone_type === 'mobile' ? Business::CHANNEL_WHATSAPP : Business::CHANNEL_EMAIL]);
                    Notification::make()->title('E-posta kaydedildi')->success()->send();

                    return;
                }

                // Cep telefonu yoksa kanal e-postaya geçer ve otomatik akışa girer.
                if ($r->contact_channel === Business::CHANNEL_MANUAL) {
                    $r->update(['contact_channel' => Business::CHANNEL_EMAIL]);

                    if ($r->site) {
                        $hasPending = $r->outreachMessages()->whereIn('status', ['draft', 'approved', 'queued'])->exists();
                        if (! $hasPending) {
                            app(MessageRenderer::class)->draft($r->load('site', 'district', 'province', 'category'));
                        }
                        $r->update(['status' => Business::STATUS_SITE_READY]);
                    } else {
                        $r->update(['status' => Business::STATUS_DISCOVERED]);
                        GenerateSiteJob::dispatch($r->id);
                    }

                    Notification::make()->title('E-posta kanalına alındı')->body('Site/mesaj taslağı onay kuyruğuna düşecek.')->success()->send();

                    return;
                }

                Notification::make()->title('E-posta kaydedildi')->success()->send();
            });
    }

    private static function callOutcomeAction(): Tables\Actions\Action
    {
        return Tables\Actions\Action::make('call')
            ->label('Arama sonucu gir')
            ->icon('heroicon-o-phone')
            ->fillForm(fn (Business $r) => ['call_outcome' => $r->call_outcome, 'call_note' => $r->call_note])
            ->form([
                Forms\Components\Select::make('call_outcome')->label('Sonuç')->options(Business::CALL_OUTCOMES)->required(),
                Forms\Components\Textarea::make('call_note')->label('Not')->rows(3),
            ])
            ->action(function (Business $r, array $data) {
                $r->update($data);

                if ($data['call_outcome'] === 'interested' && in_array($r->status, [Business::STATUS_MANUAL_CONTACT, Business::STATUS_SITE_READY, Business::STATUS_SENT, Business::STATUS_VIEWED], true)) {
                    $r->update(['status' => Business::STATUS_REPLIED]);
                }
                if ($data['call_outcome'] === 'not_interested') {
                    $r->update(['status' => Business::STATUS_LOST]);
                }
            });
    }

    private static function markWonAction(): Tables\Actions\Action
    {
        return Tables\Actions\Action::make('won')
            ->label('Satış yapıldı')
            ->icon('heroicon-o-banknotes')
            ->color('success')
            ->visible(fn (Business $r) => $r->status !== Business::STATUS_WON && $r->site !== null)
            ->fillForm(fn (Business $r) => [
                'setup_price' => $r->effectiveSetupPrice(),
                'maintenance_price' => $r->effectiveMaintenancePrice(),
                'started_at' => now()->toDateString(),
            ])
            ->form([
                Forms\Components\TextInput::make('setup_price')->label('Kurulum (TL)')->numeric()->required(),
                Forms\Components\TextInput::make('maintenance_price')->label('Aylık bakım (TL)')->numeric()->required(),
                Forms\Components\DatePicker::make('started_at')->label('Başlangıç')->required(),
            ])
            ->action(function (Business $r, array $data) {
                app(OutreachService::class)->markWon($r, (int) $data['setup_price'], (int) $data['maintenance_price'], $data['started_at']);
                Notification::make()->title('Satış kaydedildi')->success()->send();
            });
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->with(['district', 'province', 'category', 'site']);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListBusinesses::route('/'),
            'edit' => Pages\EditBusiness::route('/{record}/edit'),
        ];
    }
}
