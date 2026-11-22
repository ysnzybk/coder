<?php

namespace App\Services\Ai;

use App\Models\Business;
use App\Services\Sites\Themes;

/** Tüm LLM sağlayıcıları (Cloudflare, Claude) için ortak istem metinleri. */
class SitePrompt
{
    public static function system(): string
    {
        $palettes = implode(', ', array_keys(Themes::PALETTES));
        $fonts = implode(', ', array_keys(Themes::FONTS));

        return <<<PROMPT
        Türkiye'deki küçük yerel işletmeler için tek sayfalık tanıtım sitesi metinleri yazan deneyimli bir metin yazarı ve marka tasarımcısısın.
        Sana bir işletmenin Google Haritalar bilgileri verilecek. Bu işletmeye özel, doğal ve samimi Türkçe site içeriği üret.

        Kurallar:
        - Doğrulanamayan hiçbir bilgi uydurma: fiyat, kuruluş yılı, ödül, sertifika, kampanya, marka adı, çalışan sayısı, telefon, adres yazma.
        - Hizmet/ürün listesini işletmenin kategorisi, Google türleri ve yorumlarda geçen gerçek ifadelerden türet; emin olmadığın uzmanlıkları ekleme.
        - Yorumlar varsa öne çıkan olumlu temaları review_summary'de 1-2 cümleyle özetle; müşteri ismi kullanma. Yorum yoksa boş bırak.
        - Abartılı reklam dili ("en iyi", "bir numara") kullanma; güven veren, sade, yerel bir ton kullan.
        - highlights: 3 adet; icon alanına tek bir uygun emoji koy.
        - services: 4-6 adet; kısa ad + tek cümle açıklama.
        - faq: 3-4 adet; cevaplar genel ve doğru olmalı (ör. "Güncel bilgi için bizi arayın").
        - palette'i işletmenin sektörü ve havasına göre şunlardan seç: {$palettes}.
        - font_style'ı şunlardan seç: {$fonts}.
        - seo_title en fazla 60, seo_description en fazla 155 karakter.
        - image_prompts: İngilizce, fotogerçekçi, işletmenin sektörüne ve havasına uygun 3 görsel betimlemesi. Görselde yazı, logo, marka veya tanınabilir kişi yüzü olmasın.
        PROMPT;
    }

    public static function brief(Business $business): string
    {
        $reviews = collect($business->reviews ?? [])
            ->take(5)
            ->map(fn (array $r) => '- ('.($r['rating'] ?? '?').'/5) '.mb_substr($r['text'], 0, 600))
            ->implode("\n");

        $hours = implode('; ', $business->opening_hours ?? []);

        return implode("\n", array_filter([
            "İşletme adı: {$business->name}",
            'Kategori: '.($business->category?->name ?? 'Belirtilmemiş'),
            $business->types ? 'Kaynak türleri: '.implode(', ', $business->types) : null,
            'Konum: '.($business->district?->name ?? '').', '.($business->province?->name ?? ''),
            $business->address ? "Adres: {$business->address}" : null,
            $business->rating ? "Google puanı: {$business->rating} ({$business->review_count} yorum)" : null,
            $hours ? "Çalışma saatleri: {$hours}" : null,
            $business->notes ? "Açıklama: {$business->notes}" : null,
            $reviews ? "Müşteri yorumları:\n{$reviews}" : 'Müşteri yorumu yok.',
        ]));
    }
}
