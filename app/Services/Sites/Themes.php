<?php

namespace App\Services\Sites;

/**
 * AI'nın seçebileceği önceden tanımlı ve kontrastı test edilmiş paletler/yazı tipleri.
 * AI serbest renk üretmez; böylece her site okunaklı ve tutarlı kalır.
 */
class Themes
{
    public const PALETTES = [
        'okyanus' => ['primary' => '#0e6ba8', 'primary_dark' => '#0a4f7d', 'accent' => '#f2a541', 'bg' => '#f6fafd', 'surface' => '#ffffff', 'text' => '#13263a', 'muted' => '#4f6375'],
        'orman' => ['primary' => '#2f6b4f', 'primary_dark' => '#214c38', 'accent' => '#e0a458', 'bg' => '#f5f8f4', 'surface' => '#ffffff', 'text' => '#1d2a22', 'muted' => '#53635a'],
        'gunbatimi' => ['primary' => '#b64a33', 'primary_dark' => '#8c3624', 'accent' => '#f4b860', 'bg' => '#fdf7f2', 'surface' => '#ffffff', 'text' => '#2d1e17', 'muted' => '#6b5246'],
        'lavanta' => ['primary' => '#6c4ab6', 'primary_dark' => '#4f3589', 'accent' => '#f7a072', 'bg' => '#f8f6fc', 'surface' => '#ffffff', 'text' => '#231b33', 'muted' => '#5c5270'],
        'kahve' => ['primary' => '#7b4b2a', 'primary_dark' => '#5a361e', 'accent' => '#d9a441', 'bg' => '#faf6f1', 'surface' => '#ffffff', 'text' => '#2b1d14', 'muted' => '#6a5547'],
        'gece' => ['primary' => '#d4a24c', 'primary_dark' => '#a87d33', 'accent' => '#e8d5b0', 'bg' => '#14161b', 'surface' => '#1d2027', 'text' => '#f1ede4', 'muted' => '#b3ad9f'],
        'gul' => ['primary' => '#b83b5e', 'primary_dark' => '#8a2c46', 'accent' => '#f6c28b', 'bg' => '#fdf5f7', 'surface' => '#ffffff', 'text' => '#2e1a21', 'muted' => '#6e5059'],
        'limon' => ['primary' => '#257557', 'primary_dark' => '#1a5640', 'accent' => '#f3c623', 'bg' => '#fbfcf3', 'surface' => '#ffffff', 'text' => '#1f2a22', 'muted' => '#56645a'],
        'grafit' => ['primary' => '#c13d28', 'primary_dark' => '#962e1e', 'accent' => '#f2c14e', 'bg' => '#f4f5f7', 'surface' => '#ffffff', 'text' => '#1b1f24', 'muted' => '#555c66'],
    ];

    public const FONTS = [
        'modern' => ['heading' => 'Poppins', 'body' => 'Inter', 'query' => 'family=Poppins:wght@600;700&family=Inter:wght@400;500;600'],
        'klasik' => ['heading' => 'Playfair Display', 'body' => 'Source Sans 3', 'query' => 'family=Playfair+Display:wght@600;700&family=Source+Sans+3:wght@400;600'],
        'samimi' => ['heading' => 'Nunito', 'body' => 'Nunito', 'query' => 'family=Nunito:wght@400;600;700;800'],
        'guclu' => ['heading' => 'Oswald', 'body' => 'Roboto', 'query' => 'family=Oswald:wght@500;600&family=Roboto:wght@400;500'],
    ];

    /** Şablona göre bölüm başlıkları */
    public const LABELS = [
        'petshop' => ['services' => 'Ürün & Hizmetlerimiz', 'cta' => 'Hemen Arayın'],
        'restoran' => ['services' => 'Menüden Seçmeler', 'cta' => 'Rezervasyon / Sipariş'],
        'guzellik' => ['services' => 'Hizmetlerimiz', 'cta' => 'Randevu Alın'],
        'oto' => ['services' => 'Servislerimiz', 'cta' => 'Servis Randevusu'],
        'genel' => ['services' => 'Hizmetlerimiz', 'cta' => 'Bize Ulaşın'],
    ];

    /** Şablon → bölüm varyantları (resources/views/sites/sections/*) */
    public const VARIANTS = [
        'petshop' => ['hero' => 'fullbleed', 'services' => 'grid', 'closing' => 'banner'],
        'restoran' => ['hero' => 'split', 'services' => 'menu', 'closing' => 'gallery'],
        'guzellik' => ['hero' => 'centered', 'services' => 'list', 'closing' => 'gallery'],
        'oto' => ['hero' => 'dark', 'services' => 'tiles', 'closing' => 'banner'],
        'genel' => ['hero' => 'fullbleed', 'services' => 'grid', 'closing' => 'banner'],
    ];

    public static function variant(?string $template): array
    {
        return self::VARIANTS[$template] ?? self::VARIANTS['genel'];
    }

    /**
     * Palete okunabilirlik için türetilmiş renkler ekler:
     * on_primary (buton yazısı), hero_a/hero_b/on_hero (hero zemini ve yazısı).
     */
    public static function palette(?string $key): array
    {
        $p = self::PALETTES[$key] ?? self::PALETTES['okyanus'];

        $p['on_primary'] = self::contrast($p['primary'], '#ffffff') >= self::contrast($p['primary'], '#14161b') ? '#ffffff' : '#14161b';
        $p['on_accent'] = self::contrast($p['accent'], '#ffffff') >= self::contrast($p['accent'], '#14161b') ? '#ffffff' : '#14161b';

        if (self::luminance($p['bg']) < 0.2) {
            // Koyu palet: hero koyu zeminde açık yazı
            $p += ['hero_a' => $p['surface'], 'hero_b' => $p['bg'], 'on_hero' => $p['text']];
        } else {
            $p += ['hero_a' => $p['primary_dark'], 'hero_b' => $p['primary'], 'on_hero' => '#ffffff'];
        }

        return $p;
    }

    private static function luminance(string $hex): float
    {
        $hex = ltrim($hex, '#');
        $c = array_map(fn ($v) => hexdec($v) / 255, str_split($hex, 2));
        $c = array_map(fn ($v) => $v <= 0.03928 ? $v / 12.92 : (($v + 0.055) / 1.055) ** 2.4, $c);

        return 0.2126 * $c[0] + 0.7152 * $c[1] + 0.0722 * $c[2];
    }

    /** WCAG kontrast oranı */
    public static function contrast(string $a, string $b): float
    {
        [$l1, $l2] = [self::luminance($a), self::luminance($b)];

        return (max($l1, $l2) + 0.05) / (min($l1, $l2) + 0.05);
    }

    public static function font(?string $key): array
    {
        return self::FONTS[$key] ?? self::FONTS['modern'];
    }

    public static function labels(?string $template): array
    {
        return self::LABELS[$template] ?? self::LABELS['genel'];
    }
}
