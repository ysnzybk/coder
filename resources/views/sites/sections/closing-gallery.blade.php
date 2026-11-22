<section class="section">
    <div class="container">
        <div class="cta-card">
            <div class="cta-card-copy">
                <p class="eyebrow">{{ $place }}</p>
                <h2>{{ $c['cta_text'] ?? $labels['cta'] }}</h2>
                <p class="text">{{ $c['tagline'] ?? '' }}</p>
                <div class="hero-actions">
                    <a class="btn" href="{{ $ctaHref }}">{{ $whatsApp ? 'WhatsApp’tan Yazın' : 'Hemen Arayın' }}</a>
                    @if ($phoneTel && $whatsApp)<a class="btn btn-outline" href="tel:{{ $phoneTel }}">Ara</a>@endif
                </div>
            </div>
            <div class="cta-card-art">
                @isset($images['gallery_2'])
                    <img src="{{ $images['gallery_2'] }}" alt="" loading="lazy">
                @else
                    <div class="art-fallback" aria-hidden="true"></div>
                @endisset
            </div>
        </div>
    </div>
</section>
