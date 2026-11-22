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
Site üretimi: yorumlar + çalışma saatleri (Places Details) → Cloudflare Llama içerik (ya da Claude/şablon) → Cloudflare görseller
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

API anahtarları olmadan da çalışır: Cloudflare yoksa şablon içerik ve görselsiz (renk geçişli) site üretilir; Places yoksa Google araması çalışmaz (Overture dosyasıyla devam edilebilir).

## Ücretsiz yapı (aylık $0 hedefi)

| İş | Ücretsiz çözüm | Sınır / not |
|---|---|---|
| Firma verisi | **Google Places** ücretsiz aylık kota + **Overture Maps** (kartsız, dosya) | Google: SKU başına ≈ 1.000 istek/ay (uygulama içi sınır 900). Overture: elle doğrulama gerekir |
| Site metni | **Cloudflare Workers AI** (Llama 3.3 70B, JSON şema modu); yedek: şablon içerik | Günlük 10.000 neuron **ortak havuz** (metin + görsel), UTC 00:00'da sıfırlanır |
| Görsel | **Cloudflare FLUX-1-schnell** | Aynı havuz; bütçe dolarsa iş ertesi güne ertelenir ("Görselsiz hazırla" ile beklemeden de hazırlanabilir) |
| E-posta | Kendi SMTP hesabınız | Sağlayıcı limitine göre saatlik/günlük sınır (Ayarlar) |
| WhatsApp | wa.me bağlantısı, elle gönderim | — |

Panel → **Ayarlar → Genel ayarlar → Ücretsiz kota kullanımı** bölümünde bu ayki Google ve bugünkü Cloudflare tüketimi görünür.
Kota rakamları zamanla değişebilir; tümü `.env`'den ayarlanır. Cloudflare neuron sayacı **tahmindir** (gerçek tüketimle `CLOUDFLARE_NEURONS_PER_IMAGE` vb. ayarlayın); aşılırsa Cloudflare isteği reddeder, ücret çıkmaz.

### Google Places: ücretsiz kalmak için (önemli)
Places API'yi kullanmak için faturalandırma hesabı (kart) gerekir; ücretsiz kota aşılırsa ücret çıkar. Bunu önlemek için:
1. Google Cloud Console → **APIs & Services → Places API (New) → Quotas**: **Text Search** ve **Place Details** metotları için *günlük istek* sınırı koyun (ör. günde 30). Metotların her birinin kotası ayrıdır; sınıra gelince Google isteği reddeder (sert durdurma).
2. **Billing → Budgets & alerts**: 1 $'lık bütçe uyarısı ekleyin (uyarı yalnızca bildirir, durdurmaz).
3. Uygulama içi sayaç (`GOOGLE_PLACES_MONTHLY_*_LIMIT`) ikinci güvencedir: aylık sınıra gelince arama "Kısmi (kota)" olarak biter, site üretimi yorumsuz devam eder.

Kartsız çalışmak isterseniz Google'ı hiç tanımlamayın ve firma verisini Overture'dan alın.

### Overture Maps ile firma verisi (kartsız)
Kendi bilgisayarınızda DuckDB ile CSV üretip **Ayarlar → Dosyadan içe aktar** sayfasından (veya `php artisan places:import-file`) yüklersiniz. Adımlar: [`docs/OVERTURE.md`](docs/OVERTURE.md), sorgu: [`scripts/overture-export.sql`](scripts/overture-export.sql).
Dosya kaynaklı firmalar **"Doğrulama bekliyor"** sekmesine düşer; site üretimi, "Web'de ara" ile kontrol edip **Sitesi yok → onayla** dedikten sonra başlar (açık verideki "web sitesi yok" bilgisi eksik olabilir).

### Site metnini elle düzeltme
Firmalar → ⋯ → **İçeriği düzenle**: başlık, alt başlık, hakkımızda, hizmetler. (Siteyi "yeniden üret" ederseniz düzenlemeler silinir.) Ücretsiz Llama çıktısı Claude kadar iyi olmayabilir; şablon yedeği ve bu ekran bunun içindir.

### API anahtarları
| Değişken | Nereden | Not |
|---|---|---|
| `CLOUDFLARE_ACCOUNT_ID`, `CLOUDFLARE_API_TOKEN` | Cloudflare (ücretsiz hesap) → Workers AI → API token ("Workers AI: Read/Edit") | Metin ve görsel için tek anahtar, kart gerekmez |
| `GOOGLE_PLACES_API_KEY` | Google Cloud Console → "Places API (New)" → API anahtarı | Kart gerekir; yukarıdaki kapakları kurun |
| `MAIL_*` | Kendi SMTP hesabınız | Gmail: uygulama şifresi, port 587, `MAIL_SCHEME=null` |
| `ANTHROPIC_API_KEY` | console.anthropic.com | **Ücretli, isteğe bağlı.** `LEADGEN_AI_PROVIDER=anthropic` ile açılır |

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
