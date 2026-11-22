@php($site = $getRecord()?->business?->site)
<div>
    @if ($site)
        <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:8px;font-size:13px">
            <span style="opacity:.7">{{ $site->url() }}</span>
            <a href="{{ $site->url() }}" target="_blank" style="color:rgb(var(--primary-600));font-weight:600">Yeni sekmede aç ↗</a>
        </div>
        <div style="border:1px solid rgba(0,0,0,.1);border-radius:12px;overflow:hidden;height:640px;background:#fff">
            <iframe src="{{ $site->url() }}" style="width:100%;height:100%;border:0" loading="lazy"></iframe>
        </div>
    @else
        <p style="opacity:.7">Bu firma için henüz site üretilmedi.</p>
    @endif
</div>
