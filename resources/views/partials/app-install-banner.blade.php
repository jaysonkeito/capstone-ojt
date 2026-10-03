@php
    // The install banner only targets Android phones in a normal browser:
    // the site itself is the product on every other device, and requests
    // from inside the Android app carry the OJTTrackerApp user-agent marker
    // (capacitor.config.ts) so the banner never shows there.
    $ua = (string) request()->userAgent();
    $showInstallBanner = str_contains($ua, 'Android')
        && ! str_contains($ua, 'OJTTrackerApp');
@endphp
@if($showInstallBanner)
    <div id="appInstallBanner"
        class="relative z-40 bg-brand-600 text-white text-xs">
        <div class="max-w-7xl mx-auto px-3 sm:px-6 py-2 flex items-center gap-2">
            <svg class="shrink-0" width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><rect x="5" y="2" width="14" height="20" rx="2.5"/><path d="M11 18h2"/></svg>
            <p class="min-w-0 flex-1 truncate">
                <span class="font-semibold">Get the OJT Tracker app</span>
                <span class="text-brand-100"> — works offline, notifies you of reviews.</span>
            </p>
            <a href="{{ route('app.download') }}"
                class="shrink-0 inline-flex items-center rounded-md bg-white/95 px-2.5 py-1 text-[11px] font-semibold text-brand-700 hover:bg-white transition">Install</a>
            <button type="button" onclick="dismissAppInstallBanner()"
                class="shrink-0 -mr-1 rounded p-1 text-white/70 hover:text-white transition" aria-label="Dismiss">
                <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><path d="M18 6 6 18M6 6l12 12"/></svg>
            </button>
        </div>
    </div>
    <script>
        (function () {
            try {
                if (localStorage.getItem('ojt-app-banner-dismissed') === '1') {
                    document.getElementById('appInstallBanner')?.remove();
                }
            } catch (e) { /* private mode — keep the banner */ }
        })();
        function dismissAppInstallBanner() {
            document.getElementById('appInstallBanner')?.remove();
            try { localStorage.setItem('ojt-app-banner-dismissed', '1'); } catch (e) {}
        }
    </script>
@endif
