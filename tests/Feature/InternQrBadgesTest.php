<?php

use App\Models\Office;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('downloads every intern\'s badge QR as a ZIP of PNGs grouped by office', function () {
    $admin = makeStaff(['role' => 'admin']);
    $office = Office::create(['name' => 'MIS Office', 'type' => 'internal']);
    $placed = makeIntern(['student_id' => 'T-9301', 'office_id' => $office->id, 'last_name' => 'Placed']);
    $floating = makeIntern(['student_id' => 'T-9302', 'last_name' => 'Floating']);

    $response = $this->actingAs($admin)->get(route('admin.interns.qr-codes'));

    $response->assertOk();

    // The download is a BinaryFileResponse — the bytes live in the
    // response's file, not in getContent().
    $zip = new ZipArchive;
    $zip->open($response->getFile()->getPathname());

    $entries = [];
    for ($i = 0; $i < $zip->numFiles; $i++) {
        $entries[] = $zip->getNameIndex($i);
    }

    // Foldered by office, with the shared folder for the unplaced.
    expect($entries)->toContain('MIS Office/Placed Test (T-9301).png')
        ->and($entries)->toContain('No Office/Floating Test (T-9302).png');

    // Every file is a real PNG image.
    foreach ($entries as $entry) {
        expect(substr((string) $zip->getFromName($entry), 0, 4))->toBe("\x89PNG");
    }

    $zip->close();
    @unlink($response->getFile()->getPathname());

    // Reading the codes provisioned both interns' scan tokens.
    expect($placed->fresh()->scan_token)->not->toBeNull()
        ->and($floating->fresh()->scan_token)->not->toBeNull();
});

it('denies non-admin staff the bulk QR download', function () {
    $coordinator = makeStaff(['role' => 'coordinator', 'profile_completed_at' => now()]);

    $this->actingAs($coordinator)->get(route('admin.interns.qr-codes'))->assertForbidden();
});

it('answers 404 when there are no interns', function () {
    $admin = makeStaff(['role' => 'admin']);

    $this->actingAs($admin)->get(route('admin.interns.qr-codes'))->assertNotFound();
});
