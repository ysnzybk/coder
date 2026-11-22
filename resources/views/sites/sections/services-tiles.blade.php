<section id="hizmetler" class="section tiles-section">
    <div class="container">
        <p class="eyebrow">{{ $place }}</p>
        <h2>{{ $labels['services'] }}</h2>
        <div class="tiles">
            @foreach ($services as $i => $s)
                <article class="tile">
                    <span class="tile-no" aria-hidden="true">{{ str_pad($i + 1, 2, '0', STR_PAD_LEFT) }}</span>
                    <h3>{{ $s['name'] }}</h3>
                    <p>{{ $s['description'] }}</p>
                    <a href="{{ $ctaHref }}">{{ $labels['cta'] }} →</a>
                </article>
            @endforeach
        </div>
    </div>
</section>
