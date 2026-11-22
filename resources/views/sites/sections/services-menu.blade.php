<section id="hizmetler" class="section alt menu">
    <div class="container">
        <p class="eyebrow center">{{ $place }}</p>
        <h2 class="center">{{ $labels['services'] }}</h2>
        <div class="orn" aria-hidden="true"><span></span>✦<span></span></div>
        <div class="menu-grid">
            @foreach ($services as $i => $s)
                <article class="menu-item">
                    <div class="menu-head">
                        <h3>{{ $s['name'] }}</h3>
                        <span class="menu-dots" aria-hidden="true"></span>
                        <span class="menu-no" aria-hidden="true">{{ str_pad($i + 1, 2, '0', STR_PAD_LEFT) }}</span>
                    </div>
                    <p>{{ $s['description'] }}</p>
                </article>
            @endforeach
        </div>
        <p class="center"><a class="btn" href="{{ $ctaHref }}">{{ $c['cta_text'] ?? $labels['cta'] }}</a></p>
    </div>
</section>
