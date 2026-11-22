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
        // Ücretsiz aylık kota (Enterprise SKU ≈ 1.000 istek) altında kalmak için uygulama içi sert sınırlar.
        // Google'ın kendi sayacı esastır; bu sınırlar güvenlik payı bırakır. 0 = sınırsız (önerilmez).
        'monthly_text_limit' => (int) env('GOOGLE_PLACES_MONTHLY_TEXT_LIMIT', 900),
        'monthly_details_limit' => (int) env('GOOGLE_PLACES_MONTHLY_DETAILS_LIMIT', 900),
    ],

    /*
    | Site metni sağlayıcısı: cloudflare (ücretsiz, varsayılan) | anthropic (ücretli) | template (AI yok)
    | Seçilen sağlayıcının anahtarı yoksa otomatik olarak şablon içeriğe düşer.
    */
    'ai_provider' => env('LEADGEN_AI_PROVIDER', 'cloudflare'),

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
        'text_model' => env('CLOUDFLARE_TEXT_MODEL', '@cf/meta/llama-3.3-70b-instruct-fp8-fast'),
        'text_max_tokens' => (int) env('CLOUDFLARE_TEXT_MAX_TOKENS', 2200),
        'image_steps' => (int) env('CLOUDFLARE_IMAGE_STEPS', 4),
        // Metin + görsel ortak günlük neuron havuzu (ücretsiz 10.000; UTC 00:00'da sıfırlanır). %10 pay bırakılır.
        'daily_neuron_budget' => (int) env('CLOUDFLARE_DAILY_NEURON_BUDGET', 9000),
        // Tahmini maliyetler (canlıda gerçek tüketimle ayarlayın)
        'neurons_per_image' => (float) env('CLOUDFLARE_NEURONS_PER_IMAGE', 120),
        'neurons_per_1k_input_tokens' => (float) env('CLOUDFLARE_NEURONS_PER_1K_IN', 26.7),
        'neurons_per_1k_output_tokens' => (float) env('CLOUDFLARE_NEURONS_PER_1K_OUT', 204.8),
        'images_per_site' => (int) env('CLOUDFLARE_IMAGES_PER_SITE', 3),
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
    | Dosyadan içe aktarma (Overture Maps vb.): kaynak kategorisi → bizim sektör slug'ı.
    | Anahtarlar Overture "categories.primary" değerleridir; gerçek dosyayla teyit edin.
    */
    'file_import' => [
        'min_confidence' => (float) env('IMPORT_MIN_CONFIDENCE', 0.6),
        'category_map' => [
            'pet_store' => 'pet-shop', 'pet_shop' => 'pet-shop', 'pet_supplies' => 'pet-shop',
            'veterinarian' => 'veteriner', 'animal_hospital' => 'veteriner',
            'restaurant' => 'restoran', 'turkish_restaurant' => 'restoran', 'fast_food_restaurant' => 'restoran',
            'cafe' => 'kafe', 'coffee_shop' => 'kafe',
            'bakery' => 'pastane-firin', 'patisserie_cake_shop' => 'pastane-firin',
            'hair_salon' => 'kuafor', 'hairdresser' => 'kuafor',
            'barber' => 'berber', 'barber_shop' => 'berber',
            'beauty_salon' => 'guzellik-salonu', 'nail_salon' => 'guzellik-salonu',
            'automotive_repair' => 'oto-servis', 'car_repair' => 'oto-servis',
            'car_wash' => 'oto-yikama', 'tire_shop' => 'lastikci', 'florist' => 'cicekci',
        ],
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
