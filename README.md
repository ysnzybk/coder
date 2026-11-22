# Site Teklif Paneli

Google Haritalar'da kayıtlı ama **web sitesi olmayan** yerel işletmeleri il/ilçe ve sektör bazında bulur, her birine konseptine uygun bir tanıtım sitesi üretir, onayınızdan sonra **WhatsApp** veya **e-posta** ile teklif gönderir ve tüm süreci raporlar.

**Stack:** Laravel 12 · Filament 3 (admin) · MySQL (canlı) / SQLite (local) · Google Places API (New) · Claude API · Cloudflare Workers AI

## Akış

```
Firma Ara (il → ilçe(ler) → sektör)
  └─ Places Text Search → sonuçlar
       ├─ Gerçek web sitesi var / kalıcı kapalı ......... ELENİR
       ├─ Ret listesinde ................................ ATLANIR
       ├─ Cep telefonu (05xx) ........................... WhatsApp → site OTOMATİK üretilir
       ├─ Sabit hat + e-posta ........................... E-posta → site OTOMATİK üretilir
       └─ Sabit hat / telefon yok, e-posta yok .......... MANUEL İLETİŞİM listesi
                                                           (site "Site üret" ile isteğe bağlı,
                                                            e-posta eklenirse e-posta akışına geçer)
Site üretimi: yorumlar + çalışma saatleri (Places Details) → Claude içerik → Cloudflare görseller
  └─ Taslak mesaj → "Onay & Gönderim" ekranı (tekil / toplu onay) — ONAYSIZ HİÇBİR ŞEY GİTMEZ
       ├─ WhatsApp: "WhatsApp'ta aç" → hazır mesajla wa.me açılır, siz gönderirsiniz (gönderildi işaretlenir)
       └─ E-posta: SMTP limitine uyarak kuyruktan gönderilir (açılma pikseli + tıklama takibi)
Takip: site açıldı (link önizleme botları hariç), e-posta açıldı/tıklandı, ret (opt-out)
Satış: "Satış yapıldı" → kurulum ödemesi + her ay bakım ödemesi otomatik oluşur, MRR raporu
```

"Web sitesi yok" sayılanlar: website alanı boş **veya** yalnızca Instagram/Facebook/Linktree/WhatsApp vb. linki olanlar (`config/leadgen.php → social_domains`).

## Panel ekranları

| Ekran | İçerik |
|---|---|
| **Rapor** | İl/ilçe/sektör/tarih filtreli huni, e-posta açılma/tıklama, WhatsApp→site açılma, MRR, tahsilat, ilçe kırılımı |
| **Firma Ara** | Yeni arama, durum, bulunan/hedef sayısı, API çağrısı |
| **Firmalar** | Sekmeler: Hedefler / Manuel iletişim / Gönderildi / Satış / Kaybedilen / Elenen. Aksiyonlar: site üret, e-posta ekle, arama sonucu gir, cevap geldi, satış yapıldı, kaybedildi, CSV |
| **Onay & Gönderim** | Mesaj + canlı site önizleme yan yana, düzenle, onayla/reddet, toplu onay, WhatsApp'ta aç |
| **Satışlar & Ödemeler** | Satış, ödemeler (ödendi işaretle), firmanın kendi alan adını bağlama |
| **Ayarlar** | Varsayılan fiyat (15.000 TL + 1.000 TL/ay), gönderen adı/telefonu, SMTP limitleri, entegrasyon durumu, mesaj şablonları, sektörler, ret listesi |

Mesaj şablonu değişkenleri: `{firma_adi} {site_linki} {kurulum_ucreti} {bakim_ucreti} {ilce} {il} {ret_linki} {gonderen_adi} {gonderen_telefon}`.
Fiyat önceliği: firma → sektör → genel ayar.

## Local kurulum

```bash
composer install
cp .env.example .env && php artisan key:generate
touch database/database.sqlite
php artisan migrate --seed            # 81 il / 973 ilçe, sektörler, varsayılan şablonlar
php artisan make:filament-user        # veya .env'de ADMIN_EMAIL/ADMIN_PASSWORD + db:seed
php artisan storage:link
php artisan db:seed --class=DemoSeeder   # (opsiyonel) API anahtarı olmadan örnek firmalar
php artisan serve                     # http://127.0.0.1:8000/admin
php artisan queue:work                # ayrı terminalde
```

API anahtarları olmadan da çalışır: Claude yoksa şablon içerik, Cloudflare yoksa görselsiz (renk geçişli) site üretilir; Places yoksa arama çalışmaz.

## API anahtarları

| Değişken | Nereden | Not |
|---|---|---|
| `GOOGLE_PLACES_API_KEY` | Google Cloud Console → "Places API (New)" etkinleştir → API anahtarı | Ücretli (aylık ücretsiz kota var). Yorum/saat detayları yalnızca uygun firmalar için çekilir |
| `ANTHROPIC_API_KEY` | console.anthropic.com | Model `ANTHROPIC_MODEL` (varsayılan `claude-sonnet-5-5`) |
| `CLOUDFLARE_ACCOUNT_ID`, `CLOUDFLARE_API_TOKEN` | Cloudflare → Workers AI → API token ("Workers AI: Read/Edit") | Ücretsiz günlük kota; `CLOUDFLARE_DAILY_IMAGE_LIMIT` ile sınırlanır, dolarsa ertesi gün devam eder |
| `MAIL_*` | Kendi SMTP hesabınız | Gmail: uygulama şifresi, port 587, `MAIL_SCHEME=null` |

## cPanel (paylaşımlı hosting) dağıtımı

1. Dosyaları ana dizin dışına yükleyin (ör. `~/site-teklif`), `public/` içeriğini `public_html`'e bağlayın
   (cPanel → Domains → Document Root = `~/site-teklif/public`, ya da `public_html` yerine symlink).
2. SSH: `composer install --no-dev --optimize-autoloader`
3. `.env`: `APP_ENV=production`, `APP_DEBUG=false`, `APP_URL=https://alanadiniz.com`, `DB_CONNECTION=mysql` + bilgiler, API anahtarları, SMTP.
4. `php artisan key:generate && php artisan migrate --force --seed && php artisan storage:link`
5. `php artisan config:cache route:cache view:cache`
6. **Cron** (cPanel → Cron Jobs, her dakika):
   ```
   * * * * * cd ~/site-teklif && php artisan schedule:run >> /dev/null 2>&1
   ```
   Scheduler her dakika kuyruğu işler (`queue:work --stop-when-empty --max-time=55`, kalıcı daemon gerekmez) ve her gece bakım ödemelerini oluşturur.
7. SSL: cPanel AutoSSL ana alan adı için yeterli (siteler `alanadiniz.com/firma-adi` yolunda).

**Satış sonrası firmanın kendi alan adı:** "Satışlar" ekranında alan adını girin, ardından cPanel'de bu alan adını **Addon/Alias domain** olarak aynı document root'a ekleyin (DNS A kaydı sunucunuza). Site o alan adının ana sayfasında açılır ve arama motorlarına açılır.

## Yasal notlar

- **6563 sayılı Kanun:** esnaf/tacirlere ticari ileti için ön onay şartı yoktur, ancak **ret hakkı sunmak zorunludur**. Her mesajda ret linki bulunur; ret veren numara/e-posta kara listeye girer ve bir daha mesaj gönderilmez. Ret sayfası, link önizleme botlarının yanlışlıkla ret vermemesi için onay butonu ister.
- **Google Places:** `place_id` kalıcı saklanır; Google yorumları sitede yazar adı + "Google yorumu" atfıyla gösterilir. Places fotoğrafları kullanılmaz (görseller Cloudflare ile üretilir).
- Önizleme siteleri satılana kadar `noindex` olarak yayınlanır.

## Testler

```bash
php artisan test
```
Telefon/kanal sınıflandırma, website sınıflandırma, arama → uygunluk akışı (Places `Http::fake`), site üretimi, onaysız gönderim engeli, SMTP limiti, WhatsApp onay/giriş kontrolü, bot filtreli ziyaret takibi, e-posta açılma/tıklama, ret, satış/bakım ödemeleri.
