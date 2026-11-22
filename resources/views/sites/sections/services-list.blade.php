<section id="hizmetler" class="section alt">
    <div class="container narrow-wide">
        <p class="eyebrow center">{{ $place }}</p>
        <h2 class="center">{{ $labels['services'] }}</h2>
        <div class="rows">
            @foreach ($services as $i => $s)
                <a class="row" href="{{ $ctaHref }}">
                    <span class="row-no" aria-hidden="true">{{ str_pad($i + 1, 2, '0', STR_PAD_LEFT) }}</span>
                    <span class="row-body"><strong>{{ $s['name'] }}</strong><span>{{ $s['description'] }}</span></span>
                    <span class="row-arrow" aria-hidden="true">→</span>
                </a>
            @endforeach
        </div>
    </div>
</section>
