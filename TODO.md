# TODO ve devir notları

Son güncelleme: 2026-11-22 · Dal: `claude/local-business-website-builder-rwkah5` (GitHub'a push edildi, erişim sorunu çözüldü)

Bu dosya, çalışmaya sonradan kaldığımız yerden devam edebilmek için **tüm notları** içerir: kararlar, yapılanlar, doğrulanmayanlar, açık işler, çalışma bilgileri.

---

## 1. Proje özeti

Google Haritalar'da kayıtlı ama web sitesi olmayan yerel işletmeleri il/ilçe + sektör bazında bulur, her birine sektörüne uygun bir tanıtım sitesi üretir, **onayınızdan sonra** WhatsApp veya e-posta ile teklif gönderir ve süreci raporlar.
Teklif: kurulum 15.000 TL + aylık 1.000 TL bakım, "beğenmezseniz ödeme yok" (panelden düzenlenebilir şablon, fiyat firma → sektör → genel sırasıyla ezilir).

Stack: Laravel 12 · Filament 3 · MySQL (canlı) / SQLite (local) · Google Places API (New) + Overture Maps (açık veri) · Cloudflare Workers AI (metin + görsel) · Claude API (isteğe bağlı) · kendi SMTP · wa.me.

## 2. Alınan kararlar (kullanıcı onaylı)

| Konu | Karar |
|---|---|
| Kullanıcı | Tek admin |
| Hosting | Paylaşımlı cPanel, SSH + Composer + PHP 8.2+ var; kuyruk `database` sürücüsü + her dakika cron (`schedule:run`) |
| URL | Yol tabanlı: `APP_URL/firma-adi` (alan adı henüz yok). Satış sonrası firmanın kendi alan adı Addon domain ile bağlanır |
| "Sitesi yok" | Website alanı boş **veya** yalnızca Instagram/Facebook/Linktree/WhatsApp vb. (ayrı etiket: sadece sosyal) |
| Kanal | Cep (05xx) → WhatsApp + otomatik site. Sabit hat + e-posta → e-posta + otomatik site. İkisi de yok → **Manuel iletişim** (elenmez, site yalnızca "Site üret" ile; arama sonucu panelden girilir). E-posta Places'ten gelmez, elle eklenir |
| Onay | Tek tek ve toplu onay; **onaysız hiçbir mesaj gitmez** |
| WhatsApp | wa.me bağlantısı, kullanıcı kendi telefonundan elle gönderir; sistem "gönderildi" işaretler |
| E-posta | Kendi SMTP; açılma pikseli + tıklama yönlendirmesi; saatlik/günlük limit (varsayılan 20/100) |
| Rapor | Satış hunisi, site ziyareti, e-posta açılma/tıklama, gelir (MRR/tahsilat); il/ilçe/sektör/tarih filtreli |
| Panel | Filament |
| Görseller | Firmaya özel, ücretsiz: Cloudflare FLUX-1-schnell (Places fotoğrafları kullanılmaz, Google ToS) |
| Ücretsiz yapı | Veri: **hibrit** (Google ücretsiz kota + Overture dosyası). Metin: **Cloudflare Llama 3.3 70B**. Claude ücretli ve isteğe bağlı |
| AI asistan (restoran senaryosu) | Kapsam dışı, sonraki faz |
| Tasarım | Sektöre özel şablonlar, modern, erişilebilir. `/taste-skill` ve `/find-skill` adlı skiller hesapta **yok**; yerine `design:accessibility-review` kullanıldı |

## 3. Engeller (sizden gerekenler)

- [ ] **Alan adı** (`APP_URL`) kararı.
- [x] **Cloudflare hesabı** bağlandı (2026-11-22). Token bu oturumun kişisel ortamına (`coder`, izinli host `api.cloudflare.com`) güvenli şekilde kaydedildi, proxy ekliyor; yerel `.env`'de `CLOUDFLARE_ACCOUNT_ID` + `CLOUDFLARE_API_TOKEN=proxy-injected`. **Canlı (cPanel) `.env`'ye gerçek token'ı siz girmelisiniz.** Yeni oturumlarda `coder` ortamını seçin (token orada kayıtlı).
- [ ] **Google Places API anahtarı** (`GOOGLE_PLACES_API_KEY`): Cloud'da "Places API (New)" etkin olmalı. Kart gerekir. **Günlük kota kapakları kurulmadan canlıya çıkılmamalı** (bkz. README → Ücretsiz yapı). İstenmezse tamamen Overture ile devam edilebilir.
- [ ] **SMTP bilgileri** ve sağlayıcının günlük gönderim limiti.
- [ ] ~~Anthropic API anahtarı~~ gerekmiyor (isteğe bağlı, ücretli).

## 4. Yapıldı

- [x] Laravel 12 + Filament 3 iskeleti, migration'lar, 81 il / 973 ilçe seed (`turkey-neighbourhoods`, MIT), 12 sektör, varsayılan mesaj şablonları
- [x] Places arama (il → ilçe(ler) → sektör), ilçe dışı sonuçları eleme, tekrarı atlama, "sitesi yok" sınıflandırması, kanal kuralları
- [x] Site üretimi: yorum/saat çekme → metin (Cloudflare Llama JSON şema modu / Claude / şablon) → görseller → onay taslağı
- [x] Onay & Gönderim: tekil/toplu onay, WhatsApp wa.me, SMTP kuyruğu + limit
- [x] Takip: site ziyareti (bot filtreli), e-posta açılma/tıklama, ret (opt-out, onay butonlu) + kara liste
- [x] Satış, ödemeler, aylık bakım ödemesi otomasyonu (günlük 03:10), firmanın kendi alan adı alanı
- [x] Rapor: filtreli huni, kanal başarısı, MRR/tahsilat, ilçe kırılımı, CSV dışa aktarma
- [x] **Şablonlar:** sektöre özel hero/hizmet/kapanış varyantları (restoran: bölünmüş hero + menü, güzellik: ortalı hero + liste, oto: koyu hero + karolar, pet shop/genel: tam ekran hero + kartlar), mobil yapışkan Ara/WhatsApp çubuğu, reduced-motion uyumlu animasyonlar, palet türevleri (`on_primary`, `hero_a/b`, `on_hero`)
- [x] **Erişilebilirlik:** axe-core ile 5 şablon × 9 palet = 45 kombinasyonda WCAG 2.1 AA ihlali yok; landmark'lar, atla bağlantısı, odak halkası, 44px hedefler
- [x] **Ücretsiz yapı:**
  - Cloudflare metin üretimi (`CloudflareContentGenerator`), sağlayıcı seçimi `LEADGEN_AI_PROVIDER` (cloudflare | anthropic | template), anahtar yoksa şablona düşer
  - Ortak neuron bütçesi (`CloudflareBudget`, UTC günü, varsayılan 9.000); dolunca iş hata vermeden ertesi güne ertelenir; 429/neuron hatasında bütçe tükenmiş sayılır
  - `ContentSanitizer`: telefon/bağlantı/e-posta/TL fiyat ayıklama, uzunluk sınırı, yapı doğrulama
  - Google Places aylık sayaç (`PlacesBudget`, Pasifik saatine göre ay; varsayılan 900 arama / 900 detay); arama "Kısmi (kota)" biter, site yorumsuz üretilir
  - Overture/CSV içe aktarma: `php artisan places:import-file` + panel sayfası "Dosyadan içe aktar"; `docs/OVERTURE.md`, `scripts/overture-export.sql`
  - **Doğrulama kapısı:** dosya kaynaklı firmalar "Doğrulama bekliyor" olur, site üretilmez; Web'de ara / Sitesi yok → onayla / Sitesi var → ele (tekil ve toplu)
  - Panelden içerik düzenleme ("İçeriği düzenle"), "Görselsiz hazırla", Ayarlar'da kota göstergeleri
- [x] 37 otomatik test geçiyor, README (cPanel + ücretsiz yapı + Google kapak adımları)
- [x] **API'siz çalışma turu (2026-11-22):**
  - Rapor ekranı demo veriyle kontrol edildi; huni/kanal/gelir rakamları elle sayımla tutarlı (8 bulunan → 7 hedef → 6 site → 6 gönderilen → 4 açılan → 2 ilgili → 1 satış; MRR 1.000 ₺).
  - **Hata bulundu ve düzeltildi:** Satışlar (Deals) ekranı kayıt varken 500 veriyordu (Filament closure parametre adı). Tüm admin sayfalarını DOLU veriyle açan duman testi eklendi (`AdminPagesTest`; hatayı yakaladığı doğrulandı).
  - Bakım ödemeleri artık kaçan dönemleri de tamamlıyor (zamanlayıcı çalışmadığı aylar).
  - **Güvenlik sertleştirme:** JSON-LD'de `</script>` kaçışı (XSS), CSV dışa aktarmada formül enjeksiyonu önlemi, güvenlik başlıkları (nosniff, SAMEORIGIN, Referrer-Policy, HSTS), takip/ret uçlarına hız sınırı, canlıda https bağlantılar; `SecurityTest`.
  - `php artisan leadgen:check [--production]` (dış çağrı yapmadan canlı kontrol listesi), zamanlayıcı kalp atışı + Ayarlar'da cron göstergesi, Firmalar'da "Hatalı" sekmesi, elle düzenlenmiş içerik için yeniden üretme uyarısı.
  - Dokümanlar: `docs/DEPLOY.md` (cPanel adım adım), `scripts/deploy.sh`, `docs/EMAIL.md` (SPF/DKIM/DMARC, ısınma).
  - **Üretim kopyası denendi:** `composer install --no-dev`, config/route/view/event cache, migrate+seed, istekler (200/200/404) ve başlıklar sorunsuz.
  - **Overture SQL doğrulandı (sentetik veriyle):** DuckDB ile Overture şemasına benzer örnek parquet üzerinde çalıştırıldı, CSV → `places:import-file` hattı uçtan uca denendi. Spatial eklentisi gereksiz olduğu için `bbox.xmin/ymin` kullanılıyor. **Gerçek Overture sürümüyle hâlâ denenmedi.**
  - 46 test geçiyor.

## 5. Doğrulanmadı — canlı anahtarlarla ilk iş bunlar

Hiçbir dış servis gerçek anahtarla çalıştırılmadı; testler `Http::fake` ve örnek CSV ile yapıldı. Anahtarlar gelince **tek ilçe + tek sektörle küçük bir canlı deneme** yapılmalı:

- [x] **Cloudflare canlı deneme (2026-11-22):** token geçerli; Llama 3.3 70B **JSON şema modunu kabul etti** (≈17–19 sn/site, gerçek ≈ 145 neuron). Yanıt biçimi OpenAI uyumlu (`result.choices[0].message.content`); kod `result.response` ile ikisini de okuyor. Gerçek tüketim yanıttaki `usage.neurons` ile sayılıyor.
  - Model karşılaştırması (aynı prompt): **Llama 3.3 70B** iyi ama sıcaklık 0.5'te küçük dil hataları ("kapalıız"), 0.3'e düşürüldü; **Mistral Small 3.1 24B** daha akıcı ve yarı fiyat (≈70 neuron) ama "özel menümüz mevcut" gibi uydurma iddialar ekledi; **Llama 4 Scout** 109 sn sürdü ve soru/cevapları karıştırdı; **Gemma 3 12B** hesapta erişime kapalı (403). Karar: Llama 3.3 70B varsayılan (`CLOUDFLARE_TEXT_MODEL` ile değişir).
  - **FLUX-1-schnell:** 1024×1024 JPEG (~870 KB), ≈6 sn. Neuron = 19,2 + 38,4 × adım (2 adım 96, 3 adım 134,4, 4 adım 172,8). 2 adım görsel kalitesi yeterli → galeri 2, hero 3 adım. Kaydederken 960 px'e küçültülüp q78 sıkıştırılıyor (~100–170 KB).
  - **Bulgu:** FLUX bozuk yazılı tabela/neon üretiyor ("Obedirinni", "Lez Zetti"). `no text` ipucu işe yaramıyor. Çözüm: prompt'ta yazı içerebilecek sahneler (dış cephe, tabela, menü, insan yüzü...) yasak; riskli betimleme otomatik atılıp güvenli şablon betimlemesiyle değiştiriliyor; betimlemeler "close-up/detail" odaklı. Canlıda yeniden üretilen demo sitede bozuk yazı kalmadı.
  - Site başına ≈ 470 neuron (metin ≈145 + görseller 134+96+96) → günlük 9.000 bütçeyle **≈ 19 site/gün**.
  - **Sayaç karşılaştırması (yapıldı, tuttu):** panelde "Neurons used today" önce 2,83k idi; kontrollü denemede uygulama sayacı +481 gösterdi (metin 154 + görseller 135+96+96); panel 3,31k oldu (2,83k + 0,48k). Uygulama sayacı gerçek tüketimle birebir uyuşuyor. Toplam farkın (sayaç 2.626 vs panel ≈3.310) sebebi, uygulama dışından yaptığım elle `curl`/model karşılaştırma çağrıları (≈ 690 neuron).
  - Sayaç `Cache`'te tutulur; `cache:clear` ya da başka bir araçtan yapılan çağrılar sayaca girmez. Bu durumda Cloudflare isteği 429/neuron hatasıyla reddeder, uygulama bütçeyi tükenmiş sayıp işi ertesi UTC gününe erteler (ücret çıkmaz). Gerekirse sayaç kalıcı tabloya (`api_usage`) taşınabilir.
  - Hâlâ yapılacak: birkaç farklı sektörde (kuaför, oto, pet shop) canlı üretim kalitesine bak.
- [ ] **Google kota rakamları:** SKU başına ücretsiz 1.000 istek bilgisi 2026 tarihli bir kaynakla doğrulanamadı. Hangi SKU'ya düştüğümüz (Text Search Enterprise, Place Details Enterprise+Atmosphere) Cloud Console faturalama raporundan teyit edilmeli.
- [ ] **Places alan adları:** `places:searchText` yanıtındaki `nationalPhoneNumber`, `internationalPhoneNumber`, `websiteUri`, `businessStatus`, `reviews`, `regularOpeningHours` vb. canlı yanıtla teyit edilmeli.
- [ ] **Places `includedType` değerleri** (`pet_store`, `hair_care`, `barber_shop`, `car_repair`, `florist`...): geçersiz tür aramayı hataya düşürebilir; her sektör için dene, gerekirse Sektörler ekranından boşalt.
- [ ] **Overture gerçek dosyası:** `scripts/overture-export.sql` alan adları (`categories.primary`, `operating_status`, `addresses[1]`, `websites`, `socials`, `emails`, `phones`) ve `config/leadgen.php → file_import.category_map` anahtarları gerçek sürümle denenmedi. Türkiye kapsamı ve "web sitesi yok" doğruluğu ölçülmeli.
- [ ] **cPanel dağıtımı** gerçek sunucuda hiç denenmedi (document root, `storage:link`, cron, AutoSSL, Addon domain ile firma alan adı). Üretim kopyası yerelde denendi, rehber `docs/DEPLOY.md`.
- [ ] **E-posta teslim edilebilirliği:** kendi SMTP + takip pikseli spam'e düşebilir; SPF/DKIM/DMARC, küçük hacimle başlama, Gmail kullanılıyorsa uygulama şifresi ve günlük limit.
- [ ] **Firmalar tablosu** dar ekranda taşıyordu; bazı sütunlar varsayılan gizli, doğrulama eylemleri ikon butona çevrildi; tekrar bakılmalı.
- [ ] **Gerçek görsellerle şablonlar:** restoran şablonu gerçek FLUX görselleriyle masaüstünde kontrol edildi ve iyi görünüyor; kuaför, oto, pet shop, genel şablonları ve mobil görünüm gerçek görselle henüz bakılmadı.
- [ ] Google haritası embed'i sandbox'ta internet olmadığı için hiç görülemedi.

## 6. Bilinen eksikler / teknik borç

- [ ] İlçelerin `lat/lng` değerleri seed'de yok → Places `locationBias` kullanılmıyor; arama yalnızca metin sorgusu ("pet shop Kadıköy İstanbul") + adres metninde ilçe eşlemesi ile çalışıyor. Komşu ilçe sonuçları atılıyor ama kapsam kaçabilir; ilçe merkez koordinatları eklenebilir.
- [ ] Dosya içe aktarmada ilçe eşlemesi adres metnine bakar (en uzun ilçe adı; başka il adı geçip seçilen il geçmiyorsa atlanır). "Merkez" gibi tekrarlı ilçe adlarında yanlış eşleşme olabilir.
- [ ] Dosya içe aktarmada `category_map` sektör slug'larına bağlı (`CategorySeeder`); sektör adı/slug değişirse eşleme bozulur.
- [ ] "Siteyi yeniden üret" elle yapılan içerik düzenlemelerini siler (uyarı metni var; `content._edited` bayrağı kaydediliyor ama henüz kullanılmıyor).
- [ ] Claude reddederse şablon içeriğe düşüyor; Anthropic tarafında otomatik model geçişi (`fallbacks`) yok (PHP SDK bağlaması doğrulanamadı). Yalnızca `LEADGEN_AI_PROVIDER=anthropic` kullanılırsa önemli.
- [ ] Cloudflare neuron bütçesi `Cache` üzerinde; önbellek temizlenirse sayaç sıfırlanır (cPanel'de `CACHE_STORE=database` kalıcıdır, `file`/`array` değil).
- [ ] Panelde kullanıcı yönetimi yok; admin `php artisan make:filament-user` veya `ADMIN_EMAIL`/`ADMIN_PASSWORD` + seed ile oluşturuluyor.
- [ ] Gönderilen WhatsApp mesajı yalnızca "WhatsApp'ta aç" tıklanınca "gönderildi" sayılıyor; kullanıcının gerçekten gönderip göndermediği bilinemiyor.
- [ ] Cevap geldi / ilgileniyor durumu elle işaretleniyor (WhatsApp ve e-posta cevapları okunmuyor).
- [ ] Takip mesajı (follow-up) / yeniden gönderme akışı yok.
- [ ] Hata alan işler için toplu "tekrar dene" yok (tek tek "Site üret").
- [ ] Sitelerde çok dilli içerik, iletişim formu, çevrimiçi ödeme yok. `color-mix()` kullanıldığı için çok eski tarayıcılarda bazı yumuşak zeminler düz görünür.
- [ ] Yerel `.env`'de `APP_NAME=Laravel` kalmış olabilir ("Gönderen adı" varsayılanı buradan gelir); canlıda Ayarlar'dan gönderen adı/telefonu girilmeli.

## 7. Sonraki fazlar (kapsam dışı bırakıldı)

- [ ] Restoran senaryosu: menü/yorum analizi, mesajlara otomatik cevap veren ve rezervasyon alan AI asistan (ilk görseldeki istek).
- [ ] Gelen WhatsApp/e-posta yanıtlarını otomatik yakalama.
- [ ] Çoklu kullanıcı / roller.
- [ ] Başka ücretsiz LLM sağlayıcıları (Gemini Flash-Lite ~500/gün, Groq ~1.000/gün; limitler kaynaklarda çelişkili, panelden teyit gerekir) — `SiteContentGenerator` arayüzü hazır, yeni sınıf + `AppServiceProvider` eşlemesi yeter.
- [ ] Başka açık veri kaynakları değerlendirildi ve **seçilmedi**: OSM/Overpass (Türkiye'de dükkân telefon/website kapsamı ölçülmemiş, genel sunucu ticari kullanıma uygun değil), Foursquare OS Places (Hugging Face'te kapılı).

## 8. Yasal ve uyum notları

- 6563 sayılı Kanun: esnaf/tacire ticari ileti için ön onay şartı yok ama **ret hakkı sunmak zorunlu**. Her mesajda ret bağlantısı var; ret veren numara/e-posta kara listeye girer. Ret sayfası link önizleme botlarının yanlışlıkla ret vermemesi için onay butonu ister.
- Google Places: `place_id` kalıcı saklanır; yorumlar yazar adı + "Google yorumu" atfıyla gösterilir; Places fotoğrafları kullanılmaz.
- Önizleme siteleri satılana kadar `noindex`.
- Overture Places teması CDLA Permissive 2.0 / Apache 2.0 olarak yayımlanır; kullandığınız sürümün lisans notunu kontrol edin.
- Google ücretsiz kotası için kart gerekir; günlük kota kapağı ve bütçe uyarısı kurulmazsa aşımda ücret çıkabilir.

## 9. Sıradaki adımlar: Google Places (ayrıntılı)

Durum (2026-11-22): Cloudflare tamam ve doğrulandı. Sıradaki iş Google Places anahtarı, kota kapakları ve ilk canlı arama. Hepsi README → "Ücretsiz yapı" ile uyumlu.

### A) Sizin yapacağınız: Google Cloud hazırlığı (≈15 dk)
1. **console.cloud.google.com** → yeni proje oluşturun (ör. `site-teklif`). Mevcut projeyi karıştırmayın; kota/fatura ayrı izlensin.
2. **Faturalandırma:** projeye bir faturalandırma hesabı (kart) bağlayın. Places API'yi kullanmak için zorunlu. Yeni hesaplarda deneme kredisi olabilir; yine de aşağıdaki kapakları kurun.
3. **APIs & Services → Library → "Places API (New)" → Enable.** (Eski "Places API" değil, **New** olanı.)
4. **APIs & Services → Credentials → Create credentials → API key.** Oluşan anahtarı **API restrictions → Restrict key → Places API (New)** ile kısıtlayın. Canlı sunucu için ayrı, sunucu IP'sine kısıtlı ikinci bir anahtar önerilir (geliştirme anahtarı IP'ye kısıtlanamaz çünkü sandbox IP'si değişir).
5. **Günlük kota kapağı (önemli, ücret çıkmasın diye):** APIs & Services → Places API (New) → **Quotas & System limits**. **Text Search** ve **Place Details** metotlarının "requests per day" kotasını düşürün (ör. günde 30 / 30). Gerekirse dakikalık kotayı da düşürün. Her metodun kotası ayrıdır; sınıra gelince Google isteği reddeder (sert durdurma).
6. **Billing → Budgets & alerts:** 1 $'lık bütçe + %50/%90/%100 uyarıları. Uyarı yalnızca bildirir, durdurmaz; sert durdurmayı kota yapar.
7. Anahtarı **sohbete yazmayın**; bana "hazır" deyin, güvenli formu açarım.

### B) Benim yapacağım: bağlama ve ilk canlı deneme
1. `request_credential`: host `places.googleapis.com`, başlık `X-Goog-Api-Key`, önek yok (Cloudflare'deki gibi proxy ekler). Yerel `.env`'de `GOOGLE_PLACES_API_KEY=proxy-injected`. Ağ izni için `coder` ortamında `places.googleapis.com` ayrıca eklenir (onay ister) veya kimlik bilgisi kaydı hostu zaten açar; ikisini de dene.
2. **Küçük canlı arama:** Kadıköy + Pet shop (ya da seçeceğiniz tek ilçe/sektör). Beklenen: en fazla 3 sayfa = 3 Text Search isteği, ≤ 60 firma.
3. **Canlı yanıtla doğrulanacaklar** (şu ana kadar yalnızca sahte yanıtla test edildi):
   - alan adları: `nationalPhoneNumber`, `internationalPhoneNumber`, `websiteUri`, `businessStatus`, `rating`, `userRatingCount`, `googleMapsUri`, `location`, `types`
   - `FieldMask` kabul ediliyor mu; hangi SKU'ya düşüyoruz (Text Search Pro mu Enterprise mi?)
   - `includedType` (`pet_store`, `hair_care`, `barber_shop`, `car_repair`, `florist`...) geçerli mi; geçersizse hata mesajı ve çözüm
   - `locationBias` kullanılmıyor (ilçe lat/lng yok) → komşu ilçe sonuçları ne kadar? `inDistrict` filtresi yeterli mi?
   - kaç firma "sitesi yok" / "sadece sosyal" / "sabit hat" çıktı; kanal dağılımı
4. **Place Details (yorum + saat):** uygun bir firmada `enrich()` çalıştır: `reviews`, `regularOpeningHours`, `editorialSummary` alanları, kota sayacı.
5. **Kota sayacı karşılaştırması (Cloudflare'deki gibi):** Cloud Console → APIs & Services → Metrics/Quotas'taki istek sayısı ile uygulama sayacını (Ayarlar → Ücretsiz kota kullanımı) kıyasla; hangi SKU'ya faturalandığını Billing → Reports'tan teyit et. Ücretsiz kota rakamı (SKU başına ≈1.000) resmi sayfadan; 2026 tarihli teyit yok.
6. Bir firma için tam hat: Places verisi → yorumlu Cloudflare metni → görseller → site → onay taslağı; ekran görüntüsü.
7. Sonuçları bu dosyanın bölüm 5'ine işle, gerekirse `.env`/config sınırlarını (`GOOGLE_PLACES_MONTHLY_*_LIMIT`) ve `category.included_type` değerlerini düzelt, commit + push.

### C) Google'dan sonra (önerilen sıra)
1. Kuaför, oto, pet shop, genel şablonlarını gerçek Cloudflare görselleriyle kontrol et (restoran tamam; diğerleri yer tutucu görselle masaüstü/tablet/mobil kontrol edildi, API gerektirir).
2. ~~Rapor ekranı demo veriyle kontrol~~ yapıldı; gerçek veriyle ilk turdan sonra tekrar bak.
3. Çok sektörlü canlı üretim kalitesi (Llama Türkçesi): 3-4 farklı sektörde metinleri oku, gerekirse prompt/sanitizer ayarla.
4. **cPanel'e gerçek dağıtım** (`docs/DEPLOY.md` hazır, üretim kopyası yerelde denendi): document root, `storage:link`, cron, AutoSSL, canlı `.env`; ardından `php artisan leadgen:check --production`.
5. SMTP + SPF/DKIM/DMARC (`docs/EMAIL.md` hazır): kendi adresinize test, mail-tester.com puanı, küçük hacimli ilk gönderim; WhatsApp elle gönderim denemesi (kendi numaranıza).
6. Overture gerçek sürümüyle bir deneme (`scripts/overture-export.sql` sentetik veride doğrulandı, gerçek alan adları/kategoriler teyit edilmeli), doğrulama kapısı akışı.
7. İlk gerçek müşteri turu: tek ilçe, 5-10 firma, tek tek onay.

### Notlar
- Oturum ortamı: `coder` (kişisel, repo `ysnzybk/coder`), izinli host `api.cloudflare.com`; Cloudflare token'ı bu ortama kayıtlı. Yeni oturum açarken bu ortamı seçin.
- Yerel `.env` gitignore'da; içinde `CLOUDFLARE_ACCOUNT_ID` ve `proxy-injected` yer tutucuları var. Canlı sunucuya gerçek değerleri siz girersiniz.
- Cloudflare bugünkü kullanım (UTC): panel 3,31k/10k; yarın sıfırlanır. Günlük bütçe 9.000, site başına ≈470 neuron (≈19 site/gün).
- Places aylık sayaç sınırları varsayılan 900/900; Google'ın gerçek kotası SKU'ya göre farklı olabilir, canlı denemeden sonra ayarlanacak.
- Aylık Google kullanımını azaltmak için aramayı ilçe ilçe değil, önce küçük bir ilçeyle deneyin; her sayfa 1 istektir.
- Anahtarları/token'ları sohbete yazmayın; `request_credential` formunu kullanın.

## 10. Çalışma notları (devam ederken)

**Dosyalar**
- Kurallar/limitler: `config/leadgen.php` (tüm `.env` anahtarları `.env.example`'da)
- Kanal ve uygunluk mantığı, doğrulama kapısı: `app/Services/Places/BusinessImporter.php`
- Dosya içe aktarma: `app/Services/Places/FilePlacesImporter.php`, `app/Console/Commands/ImportPlacesFile.php`
- AI sağlayıcıları: `app/Services/Ai/*` (arayüz `SiteContentGenerator`, bağlama `AppServiceProvider`)
- Bütçeler: `app/Services/Images/CloudflareBudget.php`, `app/Services/Places/PlacesBudget.php`
- Onay ve satış: `app/Services/Outreach/OutreachService.php`
- Site şablonları: `resources/views/sites/show.blade.php` + `sections/*`, stil `public/css/site.css`, paletler `app/Services/Sites/Themes.php`
- Panel: `app/Filament/Resources/*`, `app/Filament/Pages/*`, `app/Filament/Widgets/*`

**Komutlar**
```bash
php artisan test                                   # 37 test
php artisan migrate:fresh --seed                   # il/ilçe, sektör, şablonlar
php artisan db:seed --class=DemoSeeder             # örnek firmalar (anahtar gerekmez, GD yer tutucu görsel)
php artisan queue:work --stop-when-empty           # kuyruğu işle (cPanel'de cron schedule:run bunu yapar)
php artisan places:import-file dosya.csv --il="İstanbul" --ilce="Kadıköy" --kategori=pet-shop
php artisan serve --host=127.0.0.1 --port=8000     # /admin
```

**Sandbox notları**
- Bu ortamda dış internet yok (harita, Google, Cloudflare erişilemez); `npm` kayıt defteri erişilebilir (axe-core buradan kuruldu).
- Playwright hazır; ekran görüntüsü alırken `html{scroll-behavior:smooth}` yüzünden betikte `scrollTo({behavior:'instant'})` kullanın, aksi halde `.reveal` bölümleri boş görünür (sitede hata yok).
- Filament seçim kutuları Choices.js; Playwright'ta `.choices` tıklayıp metin yazın.
- `php artisan tinker` dosya yolu alınca etkileşimli kabuğa girip takılır; betik çalıştırmak için `tinker --execute="$(cat betik.php)"` kullanın.
- Yerel demo admin: `admin@example.com` / `secret123` (yalnızca local DB, repo'da yok).

**Skiller**
- `/taste-skill` ve `/find-skill` hesapta bulunamadı. Kullanılabilir tasarım skilleri: `design:accessibility-review`, `design:design-critique`, `design:ux-copy`, `design:design-system`.

**Önerilen sıra:** bölüm 9'daki (A → B → C) sıraya bakın.
