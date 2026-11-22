<?php

namespace App\Filament\Pages;

use App\Models\Setting;
use App\Services\Images\CloudflareBudget;
use App\Services\Images\CloudflareImageGenerator;
use App\Services\Places\PlacesBudget;
use Filament\Forms;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Illuminate\Support\Facades\Cache;

class Settings extends Page implements HasForms
{
    use InteractsWithForms;

    protected static ?string $navigationIcon = 'heroicon-o-cog-6-tooth';

    protected static ?string $navigationGroup = 'Ayarlar';

    protected static ?string $navigationLabel = 'Genel ayarlar';

    protected static ?string $title = 'Genel ayarlar';

    protected static string $view = 'filament.pages.settings';

    public ?array $data = [];

    private const KEYS = ['price_setup', 'price_maintenance', 'sender_name', 'sender_phone', 'mail_per_hour', 'mail_per_day'];

    public function mount(): void
    {
        $this->form->fill([
            'price_setup' => Setting::get('price_setup', config('leadgen.pricing.setup')),
            'price_maintenance' => Setting::get('price_maintenance', config('leadgen.pricing.maintenance')),
            'sender_name' => Setting::get('sender_name', config('app.name')),
            'sender_phone' => Setting::get('sender_phone'),
            'mail_per_hour' => Setting::get('mail_per_hour', config('leadgen.mail.per_hour')),
            'mail_per_day' => Setting::get('mail_per_day', config('leadgen.mail.per_day')),
        ]);
    }

    public function form(Form $form): Form
    {
        $ok = fn (bool $v) => $v ? '✅ Tanımlı' : '❌ Tanımlı değil (.env)';
        $cf = app(CloudflareImageGenerator::class);

        return $form->statePath('data')->schema([
            Forms\Components\Section::make('Varsayılan teklif')->columns(2)->schema([
                Forms\Components\TextInput::make('price_setup')->label('Kurulum ücreti (TL)')->numeric()->required(),
                Forms\Components\TextInput::make('price_maintenance')->label('Aylık bakım (TL)')->numeric()->required(),
            ]),
            Forms\Components\Section::make('Gönderen')->columns(2)->schema([
                Forms\Components\TextInput::make('sender_name')->label('Gönderen adı / imza')->required(),
                Forms\Components\TextInput::make('sender_phone')->label('Gönderen telefonu'),
            ]),
            Forms\Components\Section::make('E-posta gönderim limiti (SMTP)')->columns(2)
                ->description('Kendi SMTP hesabınızın spam/limit kurallarına göre ayarlayın.')
                ->schema([
                    Forms\Components\TextInput::make('mail_per_hour')->label('Saatlik')->numeric()->required(),
                    Forms\Components\TextInput::make('mail_per_day')->label('Günlük')->numeric()->required(),
                ]),
            Forms\Components\Section::make('Ücretsiz kota kullanımı')->columns(3)
                ->description('Sayaçlar uygulama içi tahmindir; Google ve Cloudflare kendi sayaçlarını esas alır. Sınırlar .env ile ayarlanır.')
                ->schema([
                    Forms\Components\Placeholder::make('q_text')->label('Google arama (bu ay)')
                        ->content(fn () => app(PlacesBudget::class)->used(PlacesBudget::TEXT).' / '.(config('leadgen.places.monthly_text_limit') ?: '∞')),
                    Forms\Components\Placeholder::make('q_details')->label('Google detay (bu ay)')
                        ->content(fn () => app(PlacesBudget::class)->used(PlacesBudget::DETAILS).' / '.(config('leadgen.places.monthly_details_limit') ?: '∞')),
                    Forms\Components\Placeholder::make('q_cf')->label('Cloudflare neuron (bugün, UTC)')
                        ->content(fn () => app(CloudflareBudget::class)->used().' / '.config('leadgen.cloudflare.daily_neuron_budget')),
                ]),
            Forms\Components\Section::make('Entegrasyon durumu')->columns(2)->schema([
                Forms\Components\Placeholder::make('places')->label('Google Places API')->content($ok(filled(config('leadgen.places.key')))),
                Forms\Components\Placeholder::make('ai')->label('Site metni sağlayıcısı')
                    ->content(match (config('leadgen.ai_provider')) {
                        'cloudflare' => $cf->isConfigured() ? '✅ Cloudflare Workers AI (ücretsiz) — '.config('leadgen.cloudflare.text_model') : '⚠️ Cloudflare bilgileri yok — şablon içerik kullanılıyor',
                        'anthropic' => filled(config('leadgen.anthropic.key')) ? '✅ Claude (ücretli) — '.config('leadgen.anthropic.model') : '⚠️ Claude anahtarı yok — şablon içerik kullanılıyor',
                        default => 'Şablon içerik (AI kapalı)',
                    }),
                Forms\Components\Placeholder::make('cf')->label('Cloudflare görsel')
                    ->content($cf->isConfigured() ? '✅ FLUX-1-schnell (ücretsiz)' : '⚠️ Tanımlı değil — siteler görselsiz üretilir'),
                Forms\Components\Placeholder::make('mail')->label('SMTP')
                    ->content(config('mail.default') === 'smtp' ? '✅ '.config('mail.mailers.smtp.host') : '⚠️ MAIL_MAILER='.config('mail.default').' (gerçek gönderim yok)'),
                Forms\Components\Placeholder::make('url')->label('Site adresi (APP_URL)')->content(config('app.url')),
                Forms\Components\Placeholder::make('cron')->label('Zamanlayıcı (cron)')
                    ->content(fn () => ($beat = Cache::get('scheduler.heartbeat'))
                        ? (now()->diffInMinutes($beat) < 5 ? '✅ Çalışıyor (son: '.$beat->diffForHumans().')' : '⚠️ Son çalışma '.$beat->diffForHumans().' — cron kontrol edin')
                        : '⚠️ Henüz çalışmadı — cPanel cron: * * * * * php artisan schedule:run'),
            ]),
        ]);
    }

    public function save(): void
    {
        $data = $this->form->getState();

        foreach (self::KEYS as $key) {
            Setting::put($key, $data[$key] ?? null);
        }

        Notification::make()->title('Kaydedildi')->success()->send();
    }
}
