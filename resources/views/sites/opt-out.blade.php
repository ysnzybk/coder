<!doctype html>
<html lang="tr">
<head>
    <meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex, nofollow">
    <title>İletişim tercihi</title>
    <style>
        body{margin:0;font-family:system-ui,-apple-system,Segoe UI,Roboto,sans-serif;background:#f4f5f7;color:#1b1f24;display:grid;place-items:center;min-height:100vh;padding:16px}
        .box{background:#fff;max-width:440px;width:100%;padding:28px;border-radius:14px;box-shadow:0 8px 30px rgba(0,0,0,.06);text-align:center}
        button{background:#1b1f24;color:#fff;border:0;border-radius:8px;padding:12px 22px;font-size:15px;cursor:pointer}
        p{color:#4b5563;line-height:1.6}
    </style>
</head>
<body>
<div class="box">
    @if ($done)
        <h1>Tercihiniz kaydedildi</h1>
        <p>{{ $message->business->name }} ile ilgili size tekrar mesaj gönderilmeyecek. Rahatsızlık için özür dileriz.</p>
    @else
        <h1>İletişim istemiyorum</h1>
        <p>{{ $message->business->name }} için hazırlanan web sitesi teklifiyle ilgili tekrar mesaj almak istemiyorsanız onaylayın.</p>
        <form method="post" action="{{ url('/u/'.$message->token) }}">
            @csrf
            <button type="submit">Onaylıyorum, mesaj gönderilmesin</button>
        </form>
    @endif
</div>
</body>
</html>
