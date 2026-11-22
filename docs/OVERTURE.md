# Overture Maps ile ücretsiz firma verisi

Google Places ücretsiz kotası bittiğinde (ya da hiç kart tanımlamak istemediğinizde) açık veri kaynağı
[Overture Maps](https://docs.overturemaps.org/guides/places) kullanılabilir. API yoktur; veri dosya olarak
(GeoParquet) dağıtılır, bu yüzden **kendi bilgisayarınızda DuckDB ile CSV'ye çıkarıp** panele yüklersiniz.

## 1) CSV üret
1. DuckDB kurun: https://duckdb.org/docs/installation
2. `scripts/overture-export.sql` dosyasını açın: sürüm (`RELEASE`), bbox ve kategorileri düzenleyin.
3. `duckdb < scripts/overture-export.sql` → `places.csv`

## 2) İçe aktar
**Panel:** Ayarlar → *Dosyadan içe aktar* → il (+ isteğe bağlı ilçe/sektör) seçip CSV'yi yükleyin.

**Komut satırı:**
```bash
php artisan places:import-file places.csv --il="İstanbul" --ilce="Kadıköy" --kategori=pet-shop
```
`--kategori` verilmezse her satırın kaynak kategorisi `config/leadgen.php → file_import.category_map` ile bizim sektörlerle eşlenir.

## 3) Doğrula (zorunlu adım)
Dosya kaynaklı firmalar **"Doğrulama bekliyor"** olarak gelir ve otomatik site üretilmez. Açık verideki
"web sitesi yok" bilgisi eksik olabilir (işletmenin sitesi olup veri setinde görünmeyebilir). Firmalar ekranında:
- **Web'de ara:** Google'da işletme adı + ilçe aramasını yeni sekmede açar.
- **Sitesi yok → onayla:** normal akışa alır (kanal belirlenir, site üretilir).
- **Sitesi var → ele:** firmayı eler.

## Notlar
- Telefon yoksa ve e-posta da yoksa firma "Manuel iletişim" listesine düşer (doğrulama sonrası).
- Kategori eşlemesi ve alan adları gerçek dosyanızla teyit edilmelidir (Overture şeması sürümler arasında değişebilir).
- Lisans: Places teması CDLA Permissive 2.0 / Apache 2.0 olarak yayımlanır; sürümünüzün lisans notunu kontrol edin.
