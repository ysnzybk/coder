<!doctype html>
<html lang="tr">
<head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"></head>
<body style="margin:0;background:#f4f5f7;font-family:Arial,Helvetica,sans-serif;color:#1b1f24">
<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background:#f4f5f7;padding:24px 12px">
  <tr><td align="center">
    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="max-width:560px;background:#ffffff;border-radius:12px;padding:28px">
      <tr><td style="font-size:15px;line-height:1.6">
        {!! nl2br(preg_replace('~(https?://[^\s<]+)~', '<a href="$1" style="color:#0e6ba8">$1</a>', e($body))) !!}
      </td></tr>
      <tr><td align="center" style="padding:24px 0 8px">
        <a href="{{ $siteUrl }}" style="display:inline-block;background:#0e6ba8;color:#ffffff;text-decoration:none;padding:14px 26px;border-radius:8px;font-weight:bold">{{ $businessName }} sitesini görüntüle</a>
      </td></tr>
      <tr><td style="font-size:12px;color:#6b7280;padding-top:20px;border-top:1px solid #eee">
        Bu e-postayı tekrar almak istemiyorsanız <a href="{{ $optOutUrl }}" style="color:#6b7280">buraya tıklayın</a>.
      </td></tr>
    </table>
  </td></tr>
</table>
<img src="{{ $pixelUrl }}" width="1" height="1" alt="" style="display:block;border:0">
</body>
</html>
