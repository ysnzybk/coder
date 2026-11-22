<?php

namespace App\Services\Ai;

use Anthropic\Client;
use App\Models\Business;
use App\Services\Sites\Themes;
use Illuminate\Support\Facades\Log;
use RuntimeException;

/**
 * Claude API ile firmaya özel site içeriği üretir (structured outputs → şemaya uygun JSON).
 * Model .env'den gelir (ANTHROPIC_MODEL). Claude isteği reddederse şablon içeriğe düşer.
 */
class ClaudeContentGenerator implements SiteContentGenerator
{
    public function __construct(
        private readonly Client $client,
        private readonly TemplateContentGenerator $fallback,
    ) {}

    public function generate(Business $business): array
    {
        $fallback = $this->fallback->generate($business);

        $message = $this->client->messages->create(
            model: config('leadgen.anthropic.model'),
            maxTokens: config('leadgen.anthropic.max_tokens'),
            system: $this->systemPrompt(),
            messages: [['role' => 'user', 'content' => $this->businessBrief($business)]],
            outputConfig: [
                'effort' => config('leadgen.anthropic.effort'),
                'format' => ['type' => 'json_schema', 'schema' => ContentSchema::schema()],
            ],
        );

        if ($message->stopReason === 'refusal') {
            Log::warning('Claude içerik üretimini reddetti, şablon içerik kullanılıyor.', ['business' => $business->id]);

            return $fallback;
        }

        if ($message->stopReason === 'max_tokens') {
            throw new RuntimeException('Claude yanıtı max_tokens sınırında kesildi.');
        }

        $json = null;
        foreach ($message->content as $block) {
            if ($block->type === 'text') {
                $json = json_decode($block->text, true);
                break;
            }
        }

        if (! is_array($json)) {
            throw new RuntimeException('Claude geçerli JSON döndürmedi.');
        }

        return ['_source' => 'claude'] + ContentSchema::normalize($json, array_diff_key($fallback, ['_source' => true]));
    }

    private function systemPrompt(): string
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

    private function businessBrief(Business $business): string
    {
        $reviews = collect($business->reviews ?? [])
            ->take(5)
            ->map(fn (array $r) => '- ('.($r['rating'] ?? '?').'/5) '.mb_substr($r['text'], 0, 600))
            ->implode("\n");

        $hours = implode('; ', $business->opening_hours ?? []);

        return implode("\n", array_filter([
            "İşletme adı: {$business->name}",
            'Kategori: '.($business->category?->name ?? 'Belirtilmemiş'),
            'Google türleri: '.implode(', ', $business->types ?? []),
            'Konum: '.($business->district?->name ?? '').', '.($business->province?->name ?? ''),
            $business->address ? "Adres: {$business->address}" : null,
            $business->rating ? "Google puanı: {$business->rating} ({$business->review_count} yorum)" : null,
            $hours ? "Çalışma saatleri: {$hours}" : null,
            $business->notes ? "Google açıklaması: {$business->notes}" : null,
            $reviews ? "Müşteri yorumları:\n{$reviews}" : 'Müşteri yorumu yok.',
        ]));
    }
}
