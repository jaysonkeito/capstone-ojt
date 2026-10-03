<?php

use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

/*
 * App discovery on the web: interns mostly reach the site through a shared
 * link, so Android mobile browsers get a dismissible "Get the app" banner
 * (never on desktop, never on iOS where the app doesn't run, and never
 * inside the Android app itself — its requests carry the OJTTrackerApp
 * user-agent marker). The /download page is public and degrades to
 * "coming soon" until the APK is posted.
 */

test('desktop browsers do not see the install banner', function () {
    $this->withHeaders(['User-Agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) Chrome/126.0'])
        ->get('/login')
        ->assertOk()
        ->assertDontSee('appInstallBanner', false);
});

test('android mobile browsers see the install banner with a download link', function () {
    $this->withHeaders(['User-Agent' => 'Mozilla/5.0 (Linux; Android 14; SM-A536E) Chrome/126.0 Mobile Safari/537.36'])
        ->get('/login')
        ->assertOk()
        ->assertSee('appInstallBanner', false)
        ->assertSee(route('app.download'), false);
});

test('requests from the Android app itself never see the banner', function () {
    $this->withHeaders(['User-Agent' => 'Mozilla/5.0 (Linux; Android 14) AppleWebKit/537.36 OJTTrackerApp/1.0'])
        ->get('/login')
        ->assertOk()
        ->assertDontSee('appInstallBanner', false);
});

test('ios browsers do not see the banner while the app is android-only', function () {
    $this->withHeaders(['User-Agent' => 'Mozilla/5.0 (iPhone; CPU iPhone OS 17_5 like Mac OS X) Safari/604.1'])
        ->get('/login')
        ->assertOk()
        ->assertDontSee('appInstallBanner', false);
});

test('the download page is public and honest about availability', function () {
    // No APK posted and no APP_APK_URL configured in tests: the page renders
    // with a disabled "coming soon" button instead of a dead link.
    $this->get(route('app.download'))
        ->assertOk()
        ->assertSee('OJT Tracker for Android')
        ->assertSee('Coming soon');
});

test('the download page links straight to the apk once one is posted', function () {
    $dir = public_path('downloads');
    @mkdir($dir, 0777, true);
    file_put_contents($dir.'/ojt-tracker.apk', 'apk-bytes');

    try {
        $this->get(route('app.download'))
            ->assertOk()
            ->assertSee('downloads/ojt-tracker.apk', false);
    } finally {
        @unlink($dir.'/ojt-tracker.apk');
    }
});
