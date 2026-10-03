<?php

use Illuminate\Support\Facades\File;

uses(Illuminate\Foundation\Testing\RefreshDatabase::class);

/*
 * Offline support ships in three served pieces — the service worker, the
 * branded offline page, and the PWA manifest — plus the shared client bundle
 * that registers them. The Android app and phone browsers all rely on these;
 * each piece must stay reachable and wired into the layout.
 */

test('the service worker, offline page and manifest ship in the web root', function () {
    // Static files are served straight from public/ by the web server —
    // Laravel routing never sees them, so their presence on disk is the
    // contract (a missing file is a 404 in every environment).
    expect(File::exists(public_path('sw.js')))->toBeTrue()
        ->and(File::exists(public_path('offline.html')))->toBeTrue()
        ->and(File::json(public_path('manifest.webmanifest')))->short_name->toBe('OJT Tracker');
});

test('the built client bundle includes the offline app layer', function () {
    $manifest = json_decode(File::get(public_path('build/manifest.json')), true);

    expect($manifest)->toHaveKey('resources/js/app.js');
});

test('the app layout carries the meta tags the offline layer depends on', function () {
    $admin = makeStaff(['role' => 'admin']);

    $this->actingAs($admin)->get(route('admin.dashboard'))
        ->assertOk()
        // CSRF token for the offline journal draft replays, theme color for
        // the Android status bar, and the manifest link for Add to Home Screen.
        ->assertSee('name="csrf-token"', false)
        ->assertSee('name="theme-color"', false)
        ->assertSee('manifest.webmanifest', false);
});
