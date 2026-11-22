@if ($phoneTel || $whatsApp)
    <nav class="mobile-bar" aria-label="Hızlı iletişim">
        @if ($phoneTel)<a class="mb-call" href="tel:{{ $phoneTel }}"><span aria-hidden="true">📞</span> Ara</a>@endif
        @if ($whatsApp)<a class="mb-wa" href="https://wa.me/{{ $whatsApp }}">WhatsApp</a>@endif
    </nav>
@endif
