# TODO

Son güncelleme: 2026-11-22 · Dal: `claude/local-business-website-builder-rwkah5`

## Önce çözülmesi gerekenler (engeller)

- [ ] **GitHub erişimi:** Push 403 verdi, commit yalnızca yerelde. https://claude.ai/connect-github üzerinden GitHub'ı yeniden bağla, Claude GitHub App'in `ysnzybk/coder` reposunda kurulu olduğunu kontrol et, sonra dalı push et.
- [ ] **Alan adı** (`APP_URL`) kararı. Henüz yok; firma siteleri `APP_URL/firma-adi` altında yayınlanacak.
- [ ] **Google Places API anahtarı** (`GOOGLE_PLACES_API_KEY`): Google Cloud'da "Places API (New)" etkinleştirilmeli.
- [ ] **Anthropic API anahtarı** (`ANTHROPIC_API_KEY`).
- [ ] **Cloudflare Workers AI** bilgileri (`CLOUDFLARE_ACCOUNT_ID`, `CLOUDFLARE_API_TOKEN`).
- [ ] **SMTP bilgileri** ve sağlayıcının günlük gönderim limiti (panel ayarı varsayılanı: 20/saat, 100/gün).

## Yapıldı

- [x] Laravel 12 + Filament 3 iskeleti, MySQL/SQLite migration'ları
- [x] 81 il / 973 ilçe seed verisi (`turkey-neighbourhoods`, MIT), 12 sektör, varsayılan mesaj şablonları
- [x] Places arama: il → ilçe(ler) → sektör, ilçe dışı sonuçları eleme, tekrar eden firmayı atlama
- [x] "Sitesi yok" sınıflandırması (boş + yalnızca sosyal medya/link servisi)
- [x] Kanal kuralları: cep → WhatsApp, sabit hat + e-posta → e-posta, ikisi de yok → manuel iletişim (site isteğe bağlı)
- [x] Site üretimi: Claude içerik (structured outputs) + şablon yedeği + Cloudflare görsel + 5 sektör şablonu
- [x] Onay & Gönderim: tekil/toplu onay, WhatsApp wa.me, SMTP kuyruğu + saatlik/günlük limit
- [x] Takip: site ziyareti (bot filtreli), e-posta açılma/tıklama, ret (opt-out) + kara liste
- [x] Satış, ödemeler, aylık bakım ödemesi otomasyonu, firmanın kendi alan adı alanı
- [x] Rapor: filtreli huni, kanal başarısı, MRR/tahsilat, ilçe kırılımı, CSV dışa aktarma
- [x] 23 otomatik test geçiyor, README (cPanel kurulumu dahil)
- [x] Site şablonları modernleştirildi: sektöre özel hero/hizmet/kapanış varyantları (restoran: bölünmüş hero + menü, güzellik: ortalı hero + liste, oto: koyu hero + karolar), mobil yapışkan Ara/WhatsApp çubuğu, giriş animasyonları
- [x] Erişilebilirlik: axe-core ile 5 şablon × 9 palet (45 kombinasyon) WCAG 2.1 AA ihlali yok; landmark, atla bağlantısı, odak halkası, 44px dokunma hedefi

## Doğrulanmadı / gözden geçirilmeli

- [ ] **Gerçek API'lerle uçtan uca deneme:** Places, Claude ve Cloudflare hiç gerçek anahtarla çalıştırılmadı. Claude isteği yalnızca sahte anahtarla 401'e kadar gitti. Anahtarlar gelince tek ilçe + tek sektörle küçük bir canlı deneme yap.
- [ ] **Places alan adları:** `places:searchText` yanıtındaki `nationalPhoneNumber`, `websiteUri`, `businessStatus`, `reviews` vb. alan adları canlı yanıtla teyit edilmeli. Testler sahte yanıtla çalışıyor.
- [ ] **Places `includedType` değerleri** (`pet_store`, `hair_care`, `barber_shop` vb.): geçersiz bir tür aramayı hataya düşürebilir. Canlıda her sektör için kontrol et; gerekirse panelden boşalt.
- [ ] **Cloudflare FLUX yanıt biçimi** (`result.image` base64) ve `steps` parametresi canlıda teyit edilmeli.
- [ ] **Rapor ekranı** (Dashboard, widget'lar, filtreler) açılıyor ve hata vermiyor ama ekran görüntüsü incelenmedi. Veri azken doğru rakam gösterip göstermediği kontrol edilmeli.
- [ ] **Firmalar tablosu** dar ekranda taşıyordu; bazı sütunlar varsayılan gizli yapıldı, tekrar bakılmalı.
- [ ] **cPanel dağıtımı** hiç denenmedi (document root, `storage:link`, cron, AutoSSL).
- [ ] **E-posta teslim edilebilirliği:** kendi SMTP + takip pikseli spam'e düşebilir. SPF/DKIM/DMARC ayarları ve küçük hacimle başlama gerekli.

## Bilinen eksikler

- [ ] Şablon görselleri Cloudflare yokken GD yer tutucularıyla (yalnızca demo) denendi; gerçek FLUX görselleriyle (kırpma, aydınlık/karanlık) canlıda bir kez daha bakılmalı.
- [ ] Harita (Google embed) sandbox'ta internet olmadığı için görülemedi.
- [ ] `color-mix()` kullanılıyor; çok eski tarayıcılarda bazı yumuşak zeminler düz görünür.

- [ ] Claude içerik üretmeyi reddederse şablon içeriğe düşüyor; Anthropic tarafında otomatik model geçişi (`fallbacks`) eklenmedi (PHP SDK bağlaması doğrulanamadı).
- [ ] Panelde kullanıcı yönetimi yok; admin `make:filament-user` veya `ADMIN_EMAIL`/`ADMIN_PASSWORD` + seed ile oluşturuluyor.
- [ ] Gönderilen WhatsApp mesajı yalnızca "WhatsApp'ta aç" tıklanınca "gönderildi" sayılıyor; kullanıcı gerçekten gönderdi mi bilinemiyor.
- [ ] Cevap geldi / ilgileniyor durumu elle işaretleniyor (WhatsApp ve e-posta cevapları otomatik okunmuyor).
- [ ] Mesaj yeniden gönderme / takip mesajı (follow-up) akışı yok.
- [ ] Hata alan işler için panelde toplu "tekrar dene" yok (tek tek "Site üret" ile yapılıyor).
- [ ] Sitelerde çok dilli içerik, iletişim formu, çevrimiçi ödeme yok.

## Sonraki faz (kapsam dışı bırakıldı)

- [ ] Restoran senaryosu: menü/yorum analizi, mesajlara otomatik cevap veren ve rezervasyon alan AI asistan (ilk görseldeki istek).
- [ ] Gelen WhatsApp/e-posta yanıtlarını otomatik yakalama.
- [ ] Çoklu kullanıcı / roller (şu an tek admin).

## Çalışma notları

- Kurallar `config/leadgen.php`, kanal/uygunluk mantığı `app/Services/Places/BusinessImporter.php`, onay ve satış mantığı `app/Services/Outreach/OutreachService.php`.
- Demo veri: `php artisan db:seed --class=DemoSeeder` (API anahtarı gerektirmez).
- Testler: `php artisan test`
