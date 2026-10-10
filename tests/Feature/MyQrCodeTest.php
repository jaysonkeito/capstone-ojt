<?php

use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

/*
 * The intern's personal QR code — it lives on the Profile page now (it
 * used to be a separate My QR Code page; the old route survives as a
 * redirect for cached service-worker copies and older app deep links).
 * Uses the intern helper from InternPhotoUploadTest and the staff helper
 * from DailyReportAccessTest.
 */

test('an intern sees their personal QR code on their profile', function () {
    $intern = makeIntern();

    $this->actingAs($intern)->get(route('profile'))
        ->assertOk()
        ->assertSee('My QR Code')
        ->assertSee('data:image/png;base64', false) // the rendered QR image
        ->assertSee($intern->full_name);
});

test('opening the profile mints the intern a scan token', function () {
    $intern = makeIntern();

    expect($intern->scan_token)->toBeNull();

    $this->actingAs($intern)->get(route('profile'))->assertOk();

    expect($intern->fresh()->scan_token)->not->toBeNull();
});

test('the old my-qr route redirects to the profile', function () {
    $intern = makeIntern();

    $this->actingAs($intern)->get(route('intern.my-qr'))
        ->assertRedirect(route('profile').'#my-qr');
});

test('the profile QR section is intern-only — staff get no code', function () {
    $admin = makeStaff(['role' => 'admin', 'profile_completed_at' => now()]);

    $response = $this->actingAs($admin)->get(route('profile'))->assertOk();

    expect(substr_count($response->getContent(), 'alt="Personal time-in/out QR code"'))->toBe(0);
});

test('the profile shows a single on-screen QR plus a download button', function () {
    $intern = makeIntern();

    $response = $this->actingAs($intern)->get(route('profile'))->assertOk();

    // Exactly one QR image is rendered on screen (the ID badge) — not the old
    // two-copy layout the interns found confusing. The data URI itself also
    // appears once more inside the download script, so we count the badge's
    // image alt text instead.
    $onScreenQrCount = substr_count($response->getContent(), 'alt="Personal time-in/out QR code"');
    expect($onScreenQrCount)->toBe(1);

    $response->assertSee('Download PNG')
        ->assertSee('Present at the office scanner')
        ->assertDontSee('window.print()', false);
});
