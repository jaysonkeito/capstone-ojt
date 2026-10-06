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

beforeEach(function () {
    // Park the real release APK out of the way so availability tests see a
    // clean slate — and put it back afterwards. Deleting it outright (the
    // old try/finally) silently removed the actual distributable whenever
    // the suite ran with it in place.
    $this->apkPath = public_path('downloads/ojt-tracker.apk');
    $this->apkParked = $this->apkPath.'.test-parked';
    if (is_file($this->apkPath)) {
        rename($this->apkPath, $this->apkParked);
    }
});

afterEach(function () {
    @unlink($this->apkPath);
    if (is_file($this->apkParked)) {
        rename($this->apkParked, $this->apkPath);
    }
});

test('desktop browsers do not see the install banner', function () {
    $this->withHeaders(['User-Agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) Chrome/126.0'])
        ->get('/login')
        ->assertOk()
        // The auth layout's stylesheet mentions the banner's id in its
        // pinning rule, so assert on the rendered div itself.
        ->assertDontSee('<div id="appInstallBanner"', false);
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
        ->assertDontSee('<div id="appInstallBanner"', false);
});

test('ios browsers do not see the banner while the app is android-only', function () {
    $this->withHeaders(['User-Agent' => 'Mozilla/5.0 (iPhone; CPU iPhone OS 17_5 like Mac OS X) Safari/604.1'])
        ->get('/login')
        ->assertOk()
        ->assertDontSee('<div id="appInstallBanner"', false);
});

test('the download page is public and honest about availability', function () {
    // No APK posted (the real release APK is parked by beforeEach) and no
    // APP_APK_URL configured in tests: the page renders with a disabled
    // "coming soon" button instead of a dead link.
    $this->get(route('app.download'))
        ->assertOk()
        ->assertSee('OJT Tracker for Android')
        ->assertSee('Coming soon');
});

test('the download page links straight to the apk once one is posted', function () {
    file_put_contents(public_path('downloads/ojt-tracker.apk'), 'apk-bytes');

    $this->get(route('app.download'))
        ->assertOk()
        ->assertSee('downloads/ojt-tracker.apk', false);
});
