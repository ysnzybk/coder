<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Canlıya çıkış kontrol listesi: yapılandırmayı dış servis çağrısı YAPMADAN doğrular.
 * Kullanım: php artisan leadgen:check   (hata varsa çıkış kodu 1)
 */
class LeadgenCheck extends Command
{
    protected $signature = 'leadgen:check {--production : Canlı ortam kurallarını sıkı uygula (APP_ENV=production, debug kapalı, https)}';

    protected $description = 'Canlıya çıkış öncesi yapılandırma ve sistem kontrolü (dış servis çağrısı yapmaz)';

    /** @var array<int, array{0:string,1:string,2:string}> */
    private array $rows = [];

    private int $errors = 0;

    public function handle(): int
    {
        $prod = $this->option('production') || app()->isProduction();

        $this->check('APP_KEY', filled(config('app.key')), 'Tanımlı', '`php artisan key:generate` çalıştırın', true);
        $this->check('APP_DEBUG kapalı', ! config('app.debug') || ! $prod, config('app.debug') ? 'Açık (yalnızca geliştirme)' : 'Kapalı', 'Canlıda APP_DEBUG=false olmalı (hata ayrıntıları sızar)', $prod);
        $this->check('APP_ENV', ! $prod || app()->isProduction(), app()->environment(), 'Canlıda APP_ENV=production', $prod);
        $this->check('APP_URL https', ! $prod || str_starts_with((string) config('app.url'), 'https://'), (string) config('app.url'), 'Canlıda APP_URL=https://... olmalı (bağlantılar ve çerezler için)', $prod);
        $this->check('Oturum çerezi güvenli', ! $prod || config('session.secure') === true, var_export(config('session.secure'), true), 'Canlıda SESSION_SECURE_COOKIE=true', $prod);

        try {
            DB::connection()->getPdo();
            $this->check('Veritabanı', true, config('database.default'));
            $this->check('Veritabanı canlı sürücü', ! $prod || config('database.default') !== 'sqlite', config('database.default'), 'Canlıda MySQL kullanın', $prod);
            $this->check('Migration', ! Schema::hasTable('businesses') ? false : true, 'Uygulanmış', '`php artisan migrate --force`', true);
            $this->check('Admin kullanıcı', Schema::hasTable('users') && User::exists(), 'Var', '`php artisan make:filament-user` veya ADMIN_EMAIL/ADMIN_PASSWORD + db:seed', true);
            $this->check('İl/ilçe verisi', Schema::hasTable('provinces') && DB::table('provinces')->count() === 81, '81 il', '`php artisan db:seed --force` (LocationSeeder)', true);
        } catch (\Throwable $e) {
            $this->check('Veritabanı', false, '', 'Bağlantı kurulamadı: '.mb_substr($e->getMessage(), 0, 80), true);
        }

        $this->check('storage bağlantısı', is_link(public_path('storage')) || is_dir(public_path('storage')), 'Var', '`php artisan storage:link` (görseller için)', true);
        $this->check('storage yazılabilir', is_writable(storage_path('app/public')) && is_writable(storage_path('logs')), 'Yazılabilir', 'storage/ ve bootstrap/cache izinleri 775 olmalı', true);
        $this->check('Kuyruk sürücüsü', in_array(config('queue.default'), ['database', 'redis'], true), config('queue.default'), 'Paylaşımlı hostingde QUEUE_CONNECTION=database', true);
        $this->check('Önbellek kalıcı', ! in_array(config('cache.default'), ['array', 'null'], true), config('cache.default'), 'CACHE_STORE=database (Cloudflare/Places sayaçları burada tutulur)', true);

        $heartbeat = Cache::get('scheduler.heartbeat');
        $this->check('Zamanlayıcı (cron)', $heartbeat && now()->diffInMinutes($heartbeat) < 5, $heartbeat ? now()->diffForHumans($heartbeat) : 'hiç çalışmadı', 'cPanel cron: * * * * * cd /yol && php artisan schedule:run >> /dev/null 2>&1', $prod);

        $smtp = config('mail.default') === 'smtp';
        $this->check('SMTP', $smtp, config('mail.default'), 'MAIL_MAILER=smtp ve MAIL_HOST/USERNAME/PASSWORD/FROM_ADDRESS', $prod);
        $this->check('Gönderen adresi', ! in_array(config('mail.from.address'), [null, '', 'hello@example.com'], true), (string) config('mail.from.address'), 'MAIL_FROM_ADDRESS gerçek bir adres olmalı (SPF/DKIM için alan adınızla aynı)', $prod);

        $this->check('Google Places', filled(config('leadgen.places.key')), filled(config('leadgen.places.key')) ? 'Anahtar tanımlı' : 'Anahtar yok', 'İsteğe bağlı: anahtar yoksa Overture dosyasıyla çalışılır', false);
        $this->check('Places aylık sınırı', config('leadgen.places.monthly_text_limit') > 0 && config('leadgen.places.monthly_details_limit') > 0, config('leadgen.places.monthly_text_limit').' / '.config('leadgen.places.monthly_details_limit'), 'Sınır 0 = sınırsız (ücret riski); Cloud Console günlük kotası da koyun', false);
        $cf = filled(config('leadgen.cloudflare.account_id')) && filled(config('leadgen.cloudflare.token'));
        $this->check('Cloudflare Workers AI', $cf, $cf ? 'Tanımlı' : 'Tanımsız', 'CLOUDFLARE_ACCOUNT_ID / CLOUDFLARE_API_TOKEN (yoksa şablon içerik ve görselsiz site)', false);
        $this->check('AI sağlayıcı', true, config('leadgen.ai_provider'));
        $this->check('SMTP limitleri', (int) config('leadgen.mail.per_hour') > 0 && (int) config('leadgen.mail.per_day') > 0, config('leadgen.mail.per_hour').'/saat, '.config('leadgen.mail.per_day').'/gün', 'Sağlayıcı limitinizin altında tutun (Gmail ≈ 500/gün)', false);

        $this->table(['Kontrol', 'Durum', 'Sonuç / Not'], $this->rows);

        if ($this->errors > 0) {
            $this->error("{$this->errors} kritik sorun var.");

            return self::FAILURE;
        }

        $this->info('Kritik sorun yok.');

        return self::SUCCESS;
    }

    private function check(string $name, bool $ok, string $value, string $fix = '', bool $critical = false): void
    {
        if ($ok) {
            $this->rows[] = [$name, '✅', $value];

            return;
        }

        $this->rows[] = [$name, $critical ? '❌' : '⚠️', trim(($value !== '' ? $value.' — ' : '').$fix)];
        $this->errors += $critical ? 1 : 0;
    }
}
