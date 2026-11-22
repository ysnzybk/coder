# cPanel (paylaşımlı hosting) dağıtım rehberi

Hedef: `https://alanadiniz.com/admin` (panel) ve `https://alanadiniz.com/firma-adi` (firma siteleri).
Her adımdan sonra `php artisan leadgen:check --production` çalıştırın; sorunları ve eksikleri tek tabloda gösterir (dış servis çağrısı yapmaz).

## 0) Önkoşullar
- cPanel → **MultiPHP Manager**: alan adı için **PHP 8.2+** (8.3 önerilir).
- cPanel → **Select PHP Version / Extensions**: `mbstring`, `intl`, `gd`, `curl`, `pdo_mysql`, `zip`, `xml`, `fileinfo`, `openssl`, `ctype`, `tokenizer`, `bcmath` açık olsun.
- **SSH erişimi** ve **Composer** (cPanel'de `composer` yoksa: `curl -sS https://getcomposer.org/installer | php`).
- **MySQL** veritabanı + kullanıcı (cPanel → MySQL® Databases). Karakter seti `utf8mb4`.
- **AutoSSL** (Let's Encrypt) alan adı için etkin.

## 1) Dosyaları yerleştirin
Uygulamayı web kökünün **dışına** koyun, yalnızca `public/` içeriği yayına açılsın:

```bash
cd ~
git clone https://github.com/ysnzybk/coder.git site-teklif        # özel repo ise cPanel → Git Version Control veya zip yükleme
cd site-teklif
```

**Seçenek A (önerilen): alan adının Document Root'unu `~/site-teklif/public` yapın**
cPanel → Domains → alan adı → *Document Root* = `site-teklif/public`.

**Seçenek B: Document Root `public_html` kalacaksa**
`public_html` içine `public/` içeriğini kopyalayın/symlink'leyin ve `public_html/index.php` içindeki iki yolu uygulamanın gerçek konumuna çevirin:
```php
require __DIR__.'/../site-teklif/vendor/autoload.php';
$app = require_once __DIR__.'/../site-teklif/bootstrap/app.php';
```
(`storage:link` için `public_html/storage` → `~/site-teklif/storage/app/public` symlink'i elle oluşturun.)

## 2) Yapılandırma (`.env`)
```bash
cp .env.example .env
nano .env
```
Canlı için zorunlu değerler:

| Anahtar | Değer |
|---|---|
| `APP_ENV` | `production` |
| `APP_DEBUG` | `false` |
| `APP_URL` | `https://alanadiniz.com` (sonunda `/` yok) |
| `SESSION_SECURE_COOKIE` | `true` |
| `DB_CONNECTION` | `mysql` + `DB_HOST`, `DB_DATABASE`, `DB_USERNAME`, `DB_PASSWORD` |
| `QUEUE_CONNECTION` / `CACHE_STORE` / `SESSION_DRIVER` | `database` |
| `MAIL_MAILER` | `smtp` + `MAIL_HOST`, `MAIL_PORT`, `MAIL_USERNAME`, `MAIL_PASSWORD`, `MAIL_FROM_ADDRESS` (alan adınızla aynı), `MAIL_FROM_NAME` |
| `ADMIN_EMAIL`, `ADMIN_PASSWORD` | ilk admin (seed sırasında oluşur; sonra `.env`'den silebilirsiniz) |
| `CLOUDFLARE_ACCOUNT_ID`, `CLOUDFLARE_API_TOKEN` | gerçek değerler (**token'ı hiçbir yere yapıştırmayın, yalnızca bu dosyaya**) |
| `GOOGLE_PLACES_API_KEY` | sunucu IP'sine kısıtlı **ayrı** üretim anahtarı |

`.env` dosyası web'den erişilemez olmalı (Document Root `public/` ise zaten öyle). İzin: `chmod 600 .env`.

## 3) Kurulum
```bash
composer install --no-dev --optimize-autoloader
php artisan key:generate --force
php artisan migrate --force
php artisan db:seed --force          # il/ilçe, sektörler, mesaj şablonları, ADMIN_* ile admin
php artisan storage:link
chmod -R 775 storage bootstrap/cache
php artisan config:cache && php artisan route:cache && php artisan view:cache && php artisan event:cache
php artisan leadgen:check --production
```
Tek komutla güncelleme için: `bash scripts/deploy.sh`.

## 4) Cron (zorunlu)
cPanel → **Cron Jobs** → her dakika:
```
* * * * * cd $HOME/site-teklif && /usr/local/bin/php artisan schedule:run >> /dev/null 2>&1
```
(PHP yolunu `which php` / cPanel'in gösterdiği sürümle değiştirin, örn. `/opt/cpanel/ea-php83/root/usr/bin/php`.)
Bu satır: kuyruğu işler (kalıcı daemon gerekmez), her gece bakım ödemelerini oluşturur ve panelde "Zamanlayıcı (cron)" göstergesini besler. Birkaç dakika sonra **Ayarlar → Genel ayarlar → Zamanlayıcı** "Çalışıyor" göstermeli.

## 5) HTTPS yönlendirmesi
cPanel → Domains → "Force HTTPS Redirect" açın (veya `public/.htaccess` başına):
```apache
RewriteEngine On
RewriteCond %{HTTPS} off
RewriteRule ^ https://%{HTTP_HOST}%{REQUEST_URI} [L,R=301]
```
`APP_URL` https ise uygulama bağlantıları zaten https üretir.

## 6) Satış sonrası: firmanın kendi alan adı
1. Panel → Satışlar & Ödemeler → satışı açın → **Firmanın alan adı** alanına `firmaadi.com` yazın.
2. cPanel → **Domains → Create A New Domain / Aliases**: `firmaadi.com`'u **aynı Document Root**'a ekleyin.
3. Firmanın DNS'inde A kaydını sunucu IP'nize yönlendirin; AutoSSL sertifikayı alır.
4. Site artık `firmaadi.com` ana sayfasında açılır ve arama motorlarına açıktır (satılan site `noindex` değildir).

## 7) Yedekleme ve bakım
- **Veritabanı:** cPanel → Backup veya günlük cron: `mysqldump -u KULLANICI -p'ŞİFRE' VERITABANI | gzip > ~/backups/db-$(date +\%F).sql.gz`
- **Görseller:** `storage/app/public/sites/` (firma siteleri); yedeğe ekleyin.
- **Loglar:** `storage/logs/laravel.log` büyürse silin/döndürün.
- **Güncelleme:** `bash scripts/deploy.sh` (git pull → composer → migrate → cache). Bakım modu: `php artisan down` / `up`.

## 8) Güvenlik kontrol listesi
- [ ] `APP_DEBUG=false`, `APP_ENV=production`, `SESSION_SECURE_COOKIE=true`
- [ ] `.env` izni 600; Document Root `public/`
- [ ] Admin parolası uzun/benzersiz; `ADMIN_PASSWORD` seed sonrası `.env`'den silindi
- [ ] Google anahtarı Places API (New) + sunucu IP'siyle kısıtlı; günlük kota kapakları ve bütçe uyarısı kurulu
- [ ] Cloudflare token'ı yalnızca Workers AI iznine sahip
- [ ] SPF/DKIM/DMARC kuruldu (bkz. `docs/EMAIL.md`)
- [ ] `php artisan leadgen:check --production` temiz

## Sorun giderme
| Belirti | Bakılacak yer |
|---|---|
| 500 hatası | `storage/logs/laravel.log`; izinler (775); `.env` doğru mu; `php artisan config:clear` |
| Görseller açılmıyor | `php artisan storage:link`; `public/storage` symlink'i; APP_URL |
| İşler çalışmıyor, "Site üretiliyor"da kalıyor | Cron çalışıyor mu (panel göstergesi); `php artisan queue:work --stop-when-empty` elle; `failed_jobs` |
| E-posta gitmiyor | `MAIL_*`; `storage/logs`; sağlayıcı limiti; `docs/EMAIL.md` |
| Cloudflare "neuron" hatası | Günlük ücretsiz havuz doldu; UTC 00:00'da (TR 03:00) sıfırlanır, işler otomatik devam eder |
| Google "kota doldu" | Ayarlar → kota göstergesi; yeni ayda sıfırlanır; Overture dosyasıyla devam edilebilir |
