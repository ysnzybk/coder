@isset($images['gallery_2'])
<section class="banner" style="background-image:url('{{ $images['gallery_2'] }}')">
    <div class="banner-overlay"></div>
    <div class="container banner-content">
        <h2>{{ $c['cta_text'] ?? $labels['cta'] }}</h2>
        <a class="btn btn-accent" href="{{ $ctaHref }}">{{ $whatsApp ? 'WhatsApp’tan Yazın' : 'Hemen Arayın' }}</a>
    </div>
</section>
@endisset
