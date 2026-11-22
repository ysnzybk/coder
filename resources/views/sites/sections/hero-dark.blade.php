<section class="hero-dark {{ isset($images['hero']) ? 'has-image' : '' }}" @isset($images['hero']) style="background-image:url('{{ $images['hero'] }}')" @endisset>
    <div class="hero-dark-overlay"></div>
    <div class="container hero-dark-content">
        @if (!empty($c['tagline']))<p class="eyebrow">{{ $c['tagline'] }}</p>@endif
        <h1>{{ $c['hero_title'] ?? $business->name }}</h1>
        <p class="lead">{{ $c['hero_subtitle'] ?? '' }}</p>
        <div class="hero-actions">
            @if ($phoneTel)
                <a class="btn btn-lg" href="tel:{{ $phoneTel }}"><span aria-hidden="true">📞</span> {{ $phoneDisplay }}</a>
            @else
                <a class="btn btn-lg" href="{{ $ctaHref }}">{{ $c['cta_text'] ?? $labels['cta'] }}</a>
            @endif
            @if ($whatsApp)<a class="btn btn-ghost btn-lg" href="https://wa.me/{{ $whatsApp }}">WhatsApp</a>@endif
        </div>
        <ul class="chips">
            @if ($business->rating)<li><span aria-hidden="true">★</span> {{ number_format($business->rating, 1, ',', '') }} Google puanı</li>@endif
            @if ($business->review_count)<li>{{ $business->review_count }} değerlendirme</li>@endif
            @if ($place)<li><span aria-hidden="true">📍</span> {{ $place }}</li>@endif
        </ul>
    </div>
</section>
