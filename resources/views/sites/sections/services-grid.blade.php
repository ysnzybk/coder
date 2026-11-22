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
