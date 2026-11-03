<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $site->name }} — feedback QR poster</title>
    <style>
        /* Standalone: this poster is printed, so it carries its own stylesheet
           rather than the portal's. Everything is in millimetres-safe units. */
        * { box-sizing: border-box; }

        body {
            font-family: ui-sans-serif, system-ui, -apple-system, 'Segoe UI', Roboto, Arial, sans-serif;
            margin: 0;
            padding: 24px;
            background: #f1f5f9;
            color: #0f172a;
            -webkit-print-color-adjust: exact;
            print-color-adjust: exact;
        }

        .poster {
            max-width: 460px;
            margin: 0 auto;
            background: #fff;
            border: 1px solid #e2e8f0;
            border-radius: 20px;
            overflow: hidden;
            box-shadow: 0 10px 30px rgba(15, 23, 42, .08);
        }

        .band {
            background: #1d4ed8;
            color: #fff;
            padding: 28px 28px 24px;
            text-align: center;
        }

        .band .brand {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            font-size: .72rem;
            font-weight: 700;
            letter-spacing: .14em;
            text-transform: uppercase;
            opacity: .9;
        }

        .band h1 { font-size: 1.6rem; line-height: 1.15; margin: 12px 0 6px; letter-spacing: -.02em; }
        .band p { margin: 0; font-size: .9rem; opacity: .92; }

        .body { padding: 28px; text-align: center; }

        .qr { display: inline-block; padding: 12px; background: #fff; border: 1px solid #e2e8f0; border-radius: 16px; }
        .qr svg { display: block; width: 240px; height: auto; }

        .code {
            margin: 16px 0 0;
            font-family: ui-monospace, SFMono-Regular, Menlo, monospace;
            font-size: .78rem;
            letter-spacing: .12em;
            color: #475569;
        }

        .steps { margin: 18px 0 0; padding: 0; list-style: none; text-align: left; display: grid; gap: 8px; }
        .steps li { display: flex; gap: 10px; font-size: .84rem; color: #334155; line-height: 1.4; }
        .steps span.n {
            flex: none;
            width: 20px; height: 20px;
            border-radius: 999px;
            background: #eff6ff;
            color: #1d4ed8;
            font-size: .7rem;
            font-weight: 700;
            display: grid;
            place-items: center;
        }

        .url { margin-top: 14px; font-size: .72rem; color: #64748b; word-break: break-all; }
        .fine { margin-top: 18px; padding-top: 14px; border-top: 1px solid #e2e8f0; font-size: .7rem; color: #64748b; }

        @media print {
            body { padding: 0; background: #fff; }
            .poster { border: none; border-radius: 0; box-shadow: none; max-width: none; }
            .band { border-radius: 0; }
        }
    </style>
</head>
<body>
    <div class="poster">
        <div class="band">
            <p class="brand">DICT Free Wi-Fi</p>
            <h1>Tell us about the Wi-Fi</h1>
            <p>{{ $site->name }}@if ($site->municipality) &middot; {{ $site->municipality }}@endif</p>
        </div>

        <div class="body">
            <div class="qr" aria-label="QR code linking to the feedback form">{!! $qr !!}</div>

            <p class="code">{{ $site->site_code }}</p>

            <ol class="steps">
                <li><span class="n">1</span> Point your phone camera at the code.</li>
                <li><span class="n">2</span> Answer three quick questions.</li>
                <li><span class="n">3</span> Send. No name or phone number needed.</li>
            </ol>

            <p class="url">Or visit<br>{{ $url }}</p>

            <p class="fine">Anonymous feedback. No account, name or phone number is collected.</p>
        </div>
    </div>
</body>
</html>
