# E-posta gönderimi: teslim edilebilirlik rehberi (kendi SMTP'niz)

Soğuk e-posta spam'e düşmeye yatkındır. Aşağıdaki adımlar riski ciddi biçimde azaltır; yine de **küçük hacimle başlayın**.

## 1) Gönderen adresi ve alan adı
- Gönderici, **kendi alan adınızdan** olmalı (`info@alanadiniz.com`). `gmail.com`/`yandex.com` adresinden toplu teklif göndermek hem limit hem itibar sorunu yaratır.
- `.env`: `MAIL_FROM_ADDRESS=info@alanadiniz.com`, `MAIL_FROM_NAME="Ad Soyad | Firma"`.
- Alan adı için **SPF + DKIM + DMARC** kurulu olmalı (aşağıda).

## 2) DNS kayıtları
| Kayıt | Örnek | Not |
|---|---|---|
| **SPF** (TXT, `@`) | `v=spf1 include:_spf.google.com ~all` | Yalnızca kullandığınız sağlayıcıyı ekleyin. Google Workspace: `_spf.google.com`; cPanel sunucusu: genelde cPanel → *Email Deliverability* otomatik önerir |
| **DKIM** (TXT) | sağlayıcının verdiği `selector._domainkey` | Google Workspace: Admin → Gmail → *Authenticate email*; cPanel: *Email Deliverability* → *Manage* → DKIM'i etkinleştirin |
| **DMARC** (TXT, `_dmarc`) | `v=DMARC1; p=none; rua=mailto:dmarc@alanadiniz.com` | `p=none` ile başlayıp raporlara bakın; sorunsuzsa `quarantine` |

**cPanel'in kendi SMTP'sini kullanıyorsanız** (en kolay yol): cPanel → *Email Accounts* ile adres açın, *Email Deliverability* ekranında SPF/DKIM "geçerli" görünene kadar önerilen kaydı uygulayın. `.env`: `MAIL_HOST=mail.alanadiniz.com`, `MAIL_PORT=465` (`MAIL_SCHEME=smtps`) veya `587`, kullanıcı = tam e-posta adresi.

**Gmail/Workspace SMTP:** `smtp.gmail.com:587`, **uygulama şifresi** gerekir (2 adımlı doğrulama açık). Günlük limit yaklaşık 500 (Workspace 2.000); kişisel Gmail hesabından soğuk teklif göndermeyin.

## 3) Doğrulama
1. https://www.mail-tester.com adresindeki geçici adrese kendinizden bir test gönderin (panelde bir firmayı **kendi e-postanız** ile deneyin); 9/10 üstü hedefleyin.
2. Gmail'de gelen mesajda *Orijinali göster* → SPF/DKIM/DMARC "PASS".
3. https://mxtoolbox.com/SuperTool.aspx ile alan adının kara listede olup olmadığını kontrol edin.

## 4) Isınma (warm-up) programı
Yeni alan adı/adres için önerilen günlük gönderim (Ayarlar → *E-posta gönderim limiti* ile uygulayın):

| Gün | Günlük | Saatlik |
|---|---|---|
| 1-3 | 10 | 5 |
| 4-7 | 25 | 8 |
| 8-14 | 50 | 12 |
| 15+ | 100 | 20 |

Sıçrama yapmayın; ret/şikâyet gelirse bir basamak geri dönün.

## 5) İçerik kuralları (sistem şablonu zaten uyumludur)
- Metin + HTML birlikte gider (çok parçalı), tek bir ana bağlantı, ek dosya yok.
- Her mesajda **ret bağlantısı** ve `List-Unsubscribe` başlığı bulunur (6563 sayılı Kanun gereği zorunlu; Gmail da bunu ödüllendirir).
- Konu satırında büyük harf, ünlem ve "ücretsiz!!!" gibi spam kelimelerinden kaçının. Varsayılan şablon: "{firma_adi} için web sitenizi hazırladık".
- Aynı alıcıya tekrar tekrar göndermeyin; ret verenler otomatik kara listeye girer.
- Takip pikseli (açılma) bazı istemcilerde engellenir; açılma oranı **alt sınırdır**. Tıklama ve site ziyareti daha güvenilir göstergelerdir.

## 6) İzleme
- Panel → Rapor: e-posta açılma/tıklama oranı. Ani düşüş = spam klasörüne düşme belirtisi.
- Geri dönen (bounce) adresler SMTP sağlayıcınızın gelen kutusuna döner; geçersiz adresleri **Ret (kara liste)** ekranına ekleyin.
- `storage/logs/laravel.log` SMTP hatalarını içerir; hatalı mesajlar panelde "Hata" durumuyla görünür.

## 7) Hukuki hatırlatma
Esnaf/tacire ticari elektronik ileti için ön onay şartı yoktur; ancak **ret hakkı** sunmak ve ret verenlere tekrar göndermemek zorunludur. Kişisel e-posta adreslerine (şahıs, tacir olmayan) gönderimden kaçının.
