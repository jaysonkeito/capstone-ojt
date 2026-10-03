<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
    <meta name="theme-color" content="#4f46e5">
    <title>Get the app — OJT Tracker</title>
    <style>
        :root { color-scheme: light; }
        body { margin: 0; min-height: 100vh; display: flex; align-items: center; justify-content: center;
               background: #f3f4f6; font-family: system-ui, -apple-system, "Segoe UI", sans-serif; color: #111827; }
        .card { background: #fff; border: 1px solid #e5e7eb; border-radius: 16px; padding: 36px 30px;
                max-width: 380px; margin: 24px; text-align: center; }
        .badge { width: 56px; height: 56px; border-radius: 14px; background: #4f46e5; margin: 0 auto 18px;
                 display: flex; align-items: center; justify-content: center; }
        h1 { font-size: 18px; margin: 0 0 8px; font-weight: 600; }
        p { font-size: 13.5px; line-height: 1.6; color: #6b7280; margin: 0 0 14px; }
        a.btn, span.btn { display: block; border-radius: 10px; padding: 12px 16px; font-size: 14px; font-weight: 500;
                          text-decoration: none; margin-top: 6px; }
        a.btn { background: #4f46e5; color: #fff; }
        a.btn:active { background: #4338ca; }
        span.btn { background: #f3f4f6; color: #9ca3af; cursor: not-allowed; }
        ol { text-align: left; font-size: 12.5px; line-height: 1.6; color: #6b7280; padding-left: 1.1rem; margin: 14px 0 0; }
        .back { display: inline-block; margin-top: 18px; font-size: 12px; color: #6b7280; }
    </style>
</head>
<body>
    <div class="card">
        <div class="badge">
            <svg width="28" height="28" viewBox="0 0 24 24" fill="none" stroke="#fff" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">
                <rect x="5" y="2" width="14" height="20" rx="2.5"/><path d="M11 18h2"/>
                <path d="M12 6v6m0 0 2.2-2.2M12 12 9.8 9.8"/>
            </svg>
        </div>
        <h1>OJT Tracker for Android</h1>
        <p>Your time in/out, journals, and notifications — in one app, working even without internet.</p>

        @if($ready)
            <a class="btn" href="{{ $direct }}" download>Download the app (APK)</a>
            <ol>
                <li>Open the downloaded file on your phone.</li>
                <li>Allow "Install unknown apps" for your browser when asked.</li>
                <li>Sign in with your Student ID (or email) and password.</li>
            </ol>
        @else
            <span class="btn">Coming soon</span>
            <p style="margin-top:10px">The install file isn't posted yet. You can keep using
                <a href="{{ url('/') }}" style="color:#4f46e5; font-weight:500">the website</a> — it works fully on your phone's browser.</p>
        @endif

        <a class="back" href="{{ url('/') }}">← Back to OJT Tracker</a>
    </div>
</body>
</html>
