<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Join Kippis Squad</title>
    <style>
        :root { --kippis: #7c3aed; }
        * { box-sizing: border-box; }
        html, body { margin: 0; padding: 0; font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif; background: #faf7ff; color: #111; }
        .wrap { min-height: 100vh; display: flex; align-items: center; justify-content: center; padding: 24px; }
        .card { background: white; border-radius: 24px; padding: 40px 32px; max-width: 380px; width: 100%; text-align: center; box-shadow: 0 12px 40px rgba(124, 58, 237, 0.12); }
        h1 { margin: 0 0 8px; font-size: 28px; }
        p.sub { margin: 0 0 28px; color: #666; font-size: 15px; }
        .code-block { background: #f3eeff; color: var(--kippis); border-radius: 16px; padding: 20px; font-size: 32px; font-weight: 800; letter-spacing: 4px; margin: 0 0 24px; }
        .cta { display: inline-block; background: var(--kippis); color: white; padding: 14px 28px; border-radius: 999px; text-decoration: none; font-weight: 600; margin-bottom: 16px; }
        .stores { display: flex; gap: 12px; justify-content: center; margin-top: 24px; flex-wrap: wrap; }
        .stores a { display: inline-block; background: #111; color: white; padding: 10px 18px; border-radius: 10px; text-decoration: none; font-size: 13px; }
        .small { font-size: 12px; color: #999; margin-top: 16px; }
    </style>
</head>
<body>
    <div class="wrap">
        <div class="card">
            <h1>Join the Squad</h1>
            <p class="sub">A friend invited you to order together on Kippis.</p>

            @if ($code !== '')
                <div class="code-block">{{ $code }}</div>
                <a class="cta" href="https://kippis-eg.com/squad/join?code={{ urlencode($code) }}">Open in Kippis app</a>
            @else
                <p class="sub">Couldn't read the squad code from this link.</p>
            @endif

            <div class="stores">
                {{-- TODO: real App Store / Play Store URLs once published. --}}
                <a href="https://apps.apple.com/us/app/kippis/id6757751779">App Store</a>
                <a href="https://play.google.com/store/apps/details?id=com.raqmix.kippis">Google Play</a>
            </div>
            @if ($code !== '')
                <p class="small">Don't have the app? Install it, then re-tap the link or enter <strong>{{ $code }}</strong> manually.</p>
            @endif
        </div>
    </div>
</body>
</html>
