<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $site->name }} — feedback QR poster</title>
    <style>
        body { font-family: system-ui, sans-serif; margin: 0; padding: 24px; color: #0f172a; }
        .poster { max-width: 480px; margin: 0 auto; text-align: center; border: 2px solid #0f172a; border-radius: 12px; padding: 32px; }
        .poster svg { width: 100%; height: auto; max-width: 320px; }
        .code { font-family: ui-monospace, monospace; letter-spacing: .1em; }
        @media print { body { padding: 0; } .poster { border: none; } }
    </style>
</head>
<body>
    <div class="poster">
        <h1 style="font-size: 1.5rem; margin: 0 0 4px;">Tell us about the Wi-Fi</h1>
        <p style="margin: 0 0 16px;">{{ $site->name }}@if ($site->municipality) &middot; {{ $site->municipality }}@endif</p>

        <div aria-label="QR code linking to the feedback form">{!! $qr !!}</div>

        <p class="code" style="margin-top: 16px; font-size: 0.8rem;">{{ $site->site_code }}</p>
        <p style="margin-top: 16px; font-size: 0.8rem;">Scan the code with your phone camera, or visit<br>
            <span style="word-break: break-all;">{{ $url }}</span>
        </p>
        <p style="margin-top: 16px; font-size: 0.75rem;">Anonymous. No name or phone number collected.</p>
    </div>
</body>
</html>