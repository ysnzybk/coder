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
