@php
    $services = $c['services'] ?? [];
    $highlights = $c['highlights'] ?? [];
    $faq = $c['faq'] ?? [];
    $reviews = collect($business->reviews ?? [])->filter(fn ($r) => ($r['rating'] ?? 0) >= 4)->take(3);
    $hours = $business->opening_hours ?? [];
    $place = trim(($business->district?->name ?? '').', '.($business->province?->name ?? ''), ', ');
    $mapQuery = $business->lat ? $business->lat.','.$business->lng : $business->name.' '.$business->address;
    $ctaHref = $whatsApp ? 'https://wa.me/'.$whatsApp : ($phoneTel ? 'tel:'.$phoneTel : '#iletisim');
@endphp
<!doctype html>
<html lang="tr">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $c['seo_title'] ?? $business->name }}</title>
    <meta name="description" content="{{ $c['seo_description'] ?? '' }}">
    @if ($isPreview)<meta name="robots" content="noindex, nofollow">@endif
    <meta property="og:title" content="{{ $c['seo_title'] ?? $business->name }}">
    <meta property="og:description" content="{{ $c['seo_description'] ?? '' }}">
    @isset($images['hero'])<meta property="og:image" content="{{ $images['hero'] }}">@endisset
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?{{ $font['query'] }}&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="{{ asset('css/site.css') }}?v=1">
    <style>
        :root {
            --primary: {{ $palette['primary'] }}; --primary-dark: {{ $palette['primary_dark'] }};
            --accent: {{ $palette['accent'] }}; --bg: {{ $palette['bg'] }}; --surface: {{ $palette['surface'] }};
            --text: {{ $palette['text'] }}; --muted: {{ $palette['muted'] }};
            --font-heading: '{{ $font['heading'] }}', system-ui, sans-serif; --font-body: '{{ $font['body'] }}', system-ui, sans-serif;
        }
    </style>
    <script type="application/ld+json">{!! json_encode(array_filter([
        '@context' => 'https://schema.org',
        '@type' => 'LocalBusiness',
        'name' => $business->name,
        'address' => $business->address,
        'telephone' => $phoneTel,
        'image' => $images['hero'] ?? null,
        'aggregateRating' => $business->rating ? ['@type' => 'AggregateRating', 'ratingValue' => $business->rating, 'reviewCount' => $business->review_count] : null,
        'geo' => $business->lat ? ['@type' => 'GeoCoordinates', 'latitude' => $business->lat, 'longitude' => $business->lng] : null,
    ]), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) !!}</script>
</head>
<body class="tpl-{{ $site->template }}">

@if ($isPreview)
    <div class="preview-bar">Bu sayfa <strong>{{ $business->name }}</strong> için hazırlanmış bir önizlemedir.</div>
@endif

<header class="nav">
    <div class="container nav-inner">
        <a href="#" class="brand">{{ $business->name }}</a>
        <nav class="nav-links">
            <a href="#hizmetler">{{ $labels['services'] }}</a>
            <a href="#hakkimizda">Hakkımızda</a>
            <a href="#iletisim">İletişim</a>
        </nav>
        @if ($phoneTel)<a class="btn btn-sm" href="tel:{{ $phoneTel }}">Ara</a>@endif
    </div>
</header>

<section class="hero {{ isset($images['hero']) ? 'has-image' : '' }}" @isset($images['hero']) style="background-image:url('{{ $images['hero'] }}')" @endisset>
    <div class="hero-overlay"></div>
    <div class="container hero-content">
        @if (!empty($c['tagline']))<p class="eyebrow">{{ $c['tagline'] }}</p>@endif
        <h1>{{ $c['hero_title'] ?? $business->name }}</h1>
        <p class="lead">{{ $c['hero_subtitle'] ?? '' }}</p>
        <div class="hero-actions">
            <a class="btn btn-accent" href="{{ $ctaHref }}">{{ $c['cta_text'] ?? $labels['cta'] }}</a>
            <a class="btn btn-ghost" href="#hizmetler">{{ $labels['services'] }}</a>
        </div>
        @if ($business->rating)
            <p class="rating-pill">★ {{ number_format($business->rating, 1, ',', '') }} · Google'da {{ $business->review_count }} değerlendirme</p>
        @endif
    </div>
</section>

@if (count($highlights))
<section class="highlights">
    <div class="container grid-3">
        @foreach ($highlights as $h)
            <div class="card highlight">
                <div class="icon">{{ $h['icon'] ?? '•' }}</div>
                <h3>{{ $h['title'] }}</h3>
                <p>{{ $h['text'] }}</p>
            </div>
        @endforeach
    </div>
</section>
@endif

<section id="hakkimizda" class="section">
    <div class="container split">
        <div>
            <p class="eyebrow">Hakkımızda</p>
            <h2>{{ $business->name }}</h2>
            <p class="text">{{ $c['about'] ?? '' }}</p>
        </div>
        <div class="media">
            @isset($images['gallery_1'])
                <img src="{{ $images['gallery_1'] }}" alt="{{ $business->name }}" loading="lazy">
            @else
                <div class="media-placeholder"><span>{{ mb_substr($business->name, 0, 1) }}</span></div>
            @endisset
        </div>
    </div>
</section>

@if (count($services))
<section id="hizmetler" class="section alt">
    <div class="container">
        <p class="eyebrow center">{{ $place }}</p>
        <h2 class="center">{{ $labels['services'] }}</h2>
        <div class="grid-services">
            @foreach ($services as $s)
                <div class="card service">
                    <h3>{{ $s['name'] }}</h3>
                    <p>{{ $s['description'] }}</p>
                </div>
            @endforeach
        </div>
    </div>
</section>
@endif

@isset($images['gallery_2'])
<section class="banner" style="background-image:url('{{ $images['gallery_2'] }}')">
    <div class="banner-overlay"></div>
    <div class="container banner-content">
        <h2>{{ $c['cta_text'] ?? $labels['cta'] }}</h2>
        <a class="btn btn-accent" href="{{ $ctaHref }}">{{ $whatsApp ? 'WhatsApp’tan Yazın' : 'Hemen Arayın' }}</a>
    </div>
</section>
@endisset

@if ($reviews->count() || !empty($c['review_summary']))
<section class="section">
    <div class="container">
        <h2 class="center">Müşterilerimiz Ne Diyor?</h2>
        @if (!empty($c['review_summary']))<p class="center text narrow">{{ $c['review_summary'] }}</p>@endif
        <div class="grid-3 reviews">
            @foreach ($reviews as $r)
                <figure class="card review">
                    <div class="stars">{{ str_repeat('★', (int) ($r['rating'] ?? 5)) }}</div>
                    <blockquote>“{{ \Illuminate\Support\Str::limit($r['text'], 260) }}”</blockquote>
                    <figcaption>{{ $r['author'] }} · Google yorumu</figcaption>
                </figure>
            @endforeach
        </div>
    </div>
</section>
@endif

<section id="iletisim" class="section alt">
    <div class="container split">
        <div>
            <p class="eyebrow">İletişim</p>
            <h2>Bize Ulaşın</h2>
            <ul class="contact-list">
                @if ($business->address)<li><span>📍</span>{{ $business->address }}</li>@endif
                @if ($phoneDisplay)<li><span>📞</span><a href="tel:{{ $phoneTel }}">{{ $phoneDisplay }}</a></li>@endif
                @if ($business->email)<li><span>✉️</span><a href="mailto:{{ $business->email }}">{{ $business->email }}</a></li>@endif
                @if ($business->website_status === 'social_only' && $business->website_raw)
                    <li><span>🔗</span><a href="{{ $business->website_raw }}" rel="noopener" target="_blank">Sosyal medya</a></li>
                @endif
            </ul>
            @if (count($hours))
                <h3 class="hours-title">Çalışma Saatleri</h3>
                <ul class="hours">
                    @foreach ($hours as $line)<li>{{ $line }}</li>@endforeach
                </ul>
            @endif
            <div class="hero-actions">
                @if ($whatsApp)<a class="btn" href="https://wa.me/{{ $whatsApp }}">WhatsApp</a>@endif
                @if ($business->maps_url)<a class="btn btn-outline" href="{{ $business->maps_url }}" target="_blank" rel="noopener">Yol Tarifi</a>@endif
            </div>
        </div>
        <div class="map">
            <iframe title="Harita" loading="lazy" referrerpolicy="no-referrer-when-downgrade"
                src="https://maps.google.com/maps?q={{ urlencode($mapQuery) }}&z=16&output=embed"></iframe>
        </div>
    </div>
</section>

@if (count($faq))
<section class="section">
    <div class="container narrow">
        <h2 class="center">Sıkça Sorulan Sorular</h2>
        @foreach ($faq as $f)
            <details class="faq"><summary>{{ $f['q'] }}</summary><p>{{ $f['a'] }}</p></details>
        @endforeach
    </div>
</section>
@endif

<footer class="footer">
    <div class="container footer-inner">
        <span>© {{ date('Y') }} {{ $business->name }}</span>
        <span>{{ $place }}</span>
        @if ($isPreview && $optOutToken)
            <a href="{{ url('/u/'.$optOutToken) }}">Bu önizlemeyle ilgilenmiyorum</a>
        @endif
    </div>
</footer>

@if ($whatsApp)
    <a class="wa-float" href="https://wa.me/{{ $whatsApp }}" aria-label="WhatsApp ile yazın">
        <svg viewBox="0 0 32 32" width="28" height="28" aria-hidden="true"><path fill="currentColor" d="M16 3a13 13 0 0 0-11.2 19.6L3 29l6.6-1.7A13 13 0 1 0 16 3zm0 23.6c-2 0-4-.6-5.7-1.6l-.4-.2-3.9 1 1-3.8-.2-.4A10.6 10.6 0 1 1 16 26.6zm5.8-7.9c-.3-.2-1.9-.9-2.2-1-.3-.1-.5-.2-.7.2l-1 1.2c-.2.2-.4.2-.7.1a8.7 8.7 0 0 1-4.3-3.8c-.3-.6.3-.5.9-1.6.1-.2 0-.4 0-.5l-1-2.4c-.3-.6-.5-.5-.7-.5h-.6c-.2 0-.5.1-.8.4-.3.3-1 1-1 2.5s1.1 2.9 1.2 3.1c.2.2 2.1 3.2 5.1 4.5 1.9.8 2.6.9 3.6.7.6-.1 1.9-.8 2.1-1.5.3-.7.3-1.4.2-1.5-.1-.1-.3-.2-.6-.3z"/></svg>
    </a>
@endif
</body>
</html>
