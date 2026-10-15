<?php

use App\Models\Office;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('downloads every intern\'s badge QR as one PDF grouped by office', function () {
    $admin = makeStaff(['role' => 'admin']);
    $office = Office::create(['name' => 'MIS Office', 'type' => 'internal']);
    $placed = makeIntern(['student_id' => 'T-9301', 'office_id' => $office->id, 'last_name' => 'Placed']);
    $floating = makeIntern(['student_id' => 'T-9302', 'last_name' => 'Floating']);

    $response = $this->actingAs($admin)->get(route('admin.interns.qr-codes'));

    $response->assertOk();

    $pdf = $response->getContent();

    expect(substr($pdf, 0, 4))->toBe('%PDF')
        ->and($response->headers->get('content-disposition'))->toContain('Intern QR Codes');

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
