<?php

return [

    /*
    | Google Places API (New)
    */
    'places' => [
        'key' => env('GOOGLE_PLACES_API_KEY'),
        'base_url' => 'https://places.googleapis.com/v1',
        'language' => 'tr',
        'region' => 'TR',
        // Text Search en fazla 3 sayfa x 20 sonuç döner.
        'max_pages' => (int) env('GOOGLE_PLACES_MAX_PAGES', 3),
    ],

    /*
    | Claude (Anthropic) — site içerik üretimi
    */
    'anthropic' => [
        'key' => env('ANTHROPIC_API_KEY'),
        'model' => env('ANTHROPIC_MODEL', 'claude-sonnet-5-5'),
        'max_tokens' => 16000,
        // İçerik üretimi basit bir iş; maliyet için düşük/orta efor yeterli.
        'effort' => env('ANTHROPIC_EFFORT', 'medium'),
    ],

    /*
    | Cloudflare Workers AI — firmaya özel görsel üretimi
    */
    'cloudflare' => [
        'account_id' => env('CLOUDFLARE_ACCOUNT_ID'),
        'token' => env('CLOUDFLARE_API_TOKEN'),
        'model' => env('CLOUDFLARE_IMAGE_MODEL', '@cf/black-forest-labs/flux-1-schnell'),
        // Ücretsiz günlük kotayı aşmamak için güvenli üst sınır.
        'daily_limit' => (int) env('CLOUDFLARE_DAILY_IMAGE_LIMIT', 80),
        'images_per_site' => 3,
    ],

    /*
    | Teklif varsayılanları (panelden "Ayarlar" ile ezilebilir)
    */
    'pricing' => [
        'setup' => 15000,
        'maintenance' => 1000,
    ],

    /*
    | E-posta gönderim limiti (kendi SMTP'niz için)
    */
    'mail' => [
        'per_hour' => (int) env('OUTREACH_MAIL_PER_HOUR', 20),
        'per_day' => (int) env('OUTREACH_MAIL_PER_DAY', 100),
    ],

    /*
    | "Web sitesi yok" sayılan alan adları (sosyal medya / link servisleri)
    */
    'social_domains' => [
        'instagram.com', 'facebook.com', 'fb.com', 'm.facebook.com', 'linktr.ee',
        'wa.me', 'whatsapp.com', 'api.whatsapp.com', 'tiktok.com', 'twitter.com',
        'x.com', 'youtube.com', 'linkedin.com', 'sahibinden.com', 'yemeksepeti.com',
        'trendyol.com', 'getir.com', 'bio.link', 'beacons.ai', 'linkin.bio',
        'sites.google.com', 'business.site', 'g.page', 'goo.gl', 'maps.app.goo.gl',
    ],

    /*
    | Firma sitesi URL'lerinde kullanılamayacak slug'lar
    */
    'reserved_slugs' => [
        'admin', 'livewire', 'filament', 't', 'u', 'storage', 'build', 'api', 'up',
        'login', 'logout', 'css', 'js', 'favicon.ico', 'robots.txt', 'sitemap.xml',
    ],
];
