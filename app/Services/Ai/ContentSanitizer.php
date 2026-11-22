<?php

namespace App\Services\Ai;

/**
 * Model çıktısını güvenli hâle getirir: yapıyı doğrular, uzunlukları sınırlar,
 * uydurma olma riski yüksek içerikleri (telefon, bağlantı, TL fiyat) ayıklar.
 * Küçük/ücretsiz modellerde şema modu yapıyı garanti etse de içerik kalitesini garanti etmez.
 */
class ContentSanitizer
{
    /** Görsel modeline eklenen ortak sonek (FLUX negatif istemi desteklemez; yalnızca zayıf bir ipucu). */
    public const IMAGE_SUFFIX = ', close-up detail, no text, no signs, no lettering, no logo, no watermark';

    /** Yazı üretme riski yüksek sahneler: bu kelimeleri içeren betimleme güvenli şablon betimlemesiyle değiştirilir. */
    private const RISKY_IMAGE_WORDS = '/\b(storefront|shopfront|shop front|facade|façade|exterior|outside|signboard|signage|sign|neon|menu board|menu|poster|banner|billboard|label|logo|lettering|text|screen|book|face|portrait|person|people|man|woman|chef|customer)\b/i';

    private const LIMITS = [
        'hero_title' => 80, 'hero_subtitle' => 170, 'tagline' => 80, 'about' => 700, 'review_summary' => 320,
        'cta_text' => 40, 'seo_title' => 60, 'seo_description' => 155,
    ];

    /** @return array<string, mixed> yalnızca geçerli alanları içerir (eksikler normalize()'da tamamlanır) */
    public function clean(array $raw): array
    {
        $out = [];

        foreach (self::LIMITS as $key => $limit) {
            if (isset($raw[$key]) && is_string($raw[$key]) && ($v = $this->text($raw[$key], $limit)) !== '') {
                $out[$key] = $v;
            }
        }

        // Buton metni kısa olmalı; uzun ve kesilmiş metin yerine şablondaki varsayılan kullanılır.
        if (isset($out['cta_text']) && (mb_strlen($out['cta_text']) > 30 || str_contains($out['cta_text'], '…'))) {
            unset($out['cta_text']);
        }

        foreach (['palette', 'font_style'] as $key) {
            if (isset($raw[$key]) && is_string($raw[$key])) {
                $out[$key] = trim($raw[$key]);
            }
        }

        $out['highlights'] = $this->items($raw['highlights'] ?? [], ['icon' => 4, 'title' => 40, 'text' => 140], ['title', 'text']);
        $out['services'] = $this->items($raw['services'] ?? [], ['name' => 50, 'description' => 200], ['name', 'description']);
        $out['faq'] = $this->items($raw['faq'] ?? [], ['q' => 120, 'a' => 300], ['q', 'a']);

        if (isset($raw['image_prompts']) && is_array($raw['image_prompts'])) {
            foreach (['hero', 'gallery_1', 'gallery_2'] as $k) {
                $prompt = $raw['image_prompts'][$k] ?? null;

                // Riskli ya da boş betimlemeler atlanır; eksikler şablon betimlemesinden tamamlanır (ContentSchema::normalize)
                if (is_string($prompt) && trim($prompt) !== '' && ! preg_match(self::RISKY_IMAGE_WORDS, $prompt)) {
                    $out['image_prompts'][$k] = mb_substr(trim($prompt), 0, 300).self::IMAGE_SUFFIX;
                }
            }
        }

        return array_filter($out, fn ($v) => $v !== [] && $v !== '');
    }

    private function text(string $value, int $limit): string
    {
        $value = strip_tags($value);
        $value = preg_replace('~https?://\S+|www\.\S+|\S+@\S+\.\S+~iu', '', $value);          // bağlantı / e-posta
        $value = preg_replace('~(?<!\d)(?:\+?90|0)?[\s().-]*\d{3}[\s().-]*\d{3}[\s().-]*\d{2}[\s().-]*\d{2}(?!\d)~', '', $value); // telefon
        $value = preg_replace('~\d[\d.,]*\s*(?:₺|TL|TRY|lira)\b|(?:₺)\s*\d[\d.,]*~iu', '', $value);      // fiyat
        $value = trim(preg_replace('/\s{2,}/u', ' ', $value));

        return mb_strlen($value) > $limit ? rtrim(mb_substr($value, 0, $limit - 1)).'…' : $value;
    }

    /** @param array<string,int> $fields alan => maksimum uzunluk */
    private function items(mixed $items, array $fields, array $required): array
    {
        if (! is_array($items)) {
            return [];
        }

        $clean = [];
        foreach ($items as $item) {
            if (! is_array($item)) {
                continue;
            }
            $row = [];
            foreach ($fields as $f => $limit) {
                $row[$f] = isset($item[$f]) && is_string($item[$f]) ? $this->text($item[$f], $limit) : '';
            }
            if (collect($required)->every(fn ($f) => $row[$f] !== '')) {
                $clean[] = $row;
            }
        }

        return $clean;
    }
}
