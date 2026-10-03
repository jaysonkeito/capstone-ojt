<?php

use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

/*
 * The intern's personal QR page — shows the code the office desk scanner
 * reads (on-screen for their phone, plus a downloadable ID card PNG). Uses the
 * intern helper from InternPhotoUploadTest and the staff helper from
 * DailyReportAccessTest.
 */

test('an intern can view their personal QR code', function () {
    $intern = makeIntern();

    $this->actingAs($intern)->get(route('intern.my-qr'))
        ->assertOk()
        ->assertSee('My QR Code')
        ->assertSee('data:image/png;base64', false) // the rendered QR image
        ->assertSee($intern->full_name);
});

test('opening the page mints the intern a scan token', function () {
    $intern = makeIntern();

    expect($intern->scan_token)->toBeNull();

    $this->actingAs($intern)->get(route('intern.my-qr'))->assertOk();

    expect($intern->fresh()->scan_token)->not->toBeNull();
});

test('the personal QR page is intern-only', function () {
    $admin = makeStaff(['role' => 'admin']);
    $coordinator = makeStaff(['role' => 'coordinator']);

    $this->actingAs($admin)->get(route('intern.my-qr'))->assertForbidden();
    $this->actingAs($coordinator)->get(route('intern.my-qr'))->assertForbidden();
});

test('the page shows a single on-screen QR plus a download button', function () {
    $intern = makeIntern();

    $response = $this->actingAs($intern)->get(route('intern.my-qr'))->assertOk();

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
