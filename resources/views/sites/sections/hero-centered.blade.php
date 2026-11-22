<section class="hero-centered">
    <div class="container">
        <div class="hero-centered-copy">
            @if (!empty($c['tagline']))<p class="eyebrow">{{ $c['tagline'] }}</p>@endif
            <h1>{{ $c['hero_title'] ?? $business->name }}</h1>
            <p class="lead">{{ $c['hero_subtitle'] ?? '' }}</p>
            <div class="hero-actions center-actions">
                <a class="btn" href="{{ $ctaHref }}">{{ $c['cta_text'] ?? $labels['cta'] }}</a>
                <a class="btn btn-outline" href="#hizmetler">{{ $labels['services'] }}</a>
            </div>
            @if ($business->rating)
                <p class="rating-line"><span class="stars" aria-hidden="true">★★★★★</span> {{ number_format($business->rating, 1, ',', '') }} · {{ $business->review_count }} Google değerlendirmesi</p>
            @endif
        </div>
        <div class="hero-centered-art">
            @isset($images['hero'])
                <img src="{{ $images['hero'] }}" alt="{{ $business->name }}" fetchpriority="high">
            @else
                <div class="art-fallback wide" aria-hidden="true"><span>{{ mb_substr($business->name, 0, 1) }}</span></div>
            @endisset
        </div>
    </div>
</section>
