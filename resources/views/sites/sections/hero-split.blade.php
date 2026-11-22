<section class="hero-split">
    <div class="container hero-split-grid">
        <div class="hero-copy">
            @if (!empty($c['tagline']))<p class="eyebrow">{{ $c['tagline'] }}</p>@endif
            <h1>{{ $c['hero_title'] ?? $business->name }}</h1>
            <p class="lead">{{ $c['hero_subtitle'] ?? '' }}</p>
            <div class="hero-actions">
                <a class="btn" href="{{ $ctaHref }}">{{ $c['cta_text'] ?? $labels['cta'] }}</a>
                <a class="btn btn-outline" href="#hizmetler">{{ $labels['services'] }}</a>
            </div>
            @if ($business->rating)
                <p class="rating-line"><span class="stars" aria-hidden="true">★★★★★</span> {{ number_format($business->rating, 1, ',', '') }} · Google'da {{ $business->review_count }} değerlendirme</p>
            @endif
        </div>
        <div class="hero-art">
            @isset($images['hero'])
                <img class="arch" src="{{ $images['hero'] }}" alt="{{ $business->name }}" fetchpriority="high">
            @else
                <div class="arch art-fallback" aria-hidden="true"><span>{{ mb_substr($business->name, 0, 1) }}</span></div>
            @endisset
            @if ($place)<span class="art-tag"><span aria-hidden="true">📍</span> {{ $place }}</span>@endif
        </div>
    </div>
</section>
