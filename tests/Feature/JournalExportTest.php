<?php

use App\Models\OjtLog;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

uses(RefreshDatabase::class);

/*
 * The Daily Journal tab's export — the intern's duty journals compiled into
 * ONE PDF (one page per day, the exact Daily Report layout) with four
 * selections: Daily (one date), Weekly (any day's Mon–Sun week), Range
 * (inclusive from→to), and All (every journal on record).
 *
 * Reuses the global helpers makeIntern / makeStaff / makeActiveEnrollment /
 * makeLog / fullDutyTimes defined in the sibling feature tests.
 */

beforeEach(function () {
    Storage::fake('public');
});

function journalScenario(): array
{
    $admin = makeStaff(['role' => 'admin']);
    $intern = makeIntern(['last_name' => 'Exporta', 'first_name' => 'Nida']);
    $enrollment = makeActiveEnrollment($intern);

    // Two duty days in the same week (Tue + Thu), one further out.
    $weekLog1 = makeLog($intern, $enrollment, date: '2026-09-08', times: fullDutyTimes());
    $weekLog2 = makeLog($intern, $enrollment, date: '2026-09-10', times: fullDutyTimes());
    $outsideLog = makeLog($intern, $enrollment, date: '2026-09-21', times: fullDutyTimes());

    // Give one journal a real photo so the compiled PDF carries an image.
    $img = imagecreatetruecolor(40, 40);
    imagefill($img, 0, 0, imagecolorallocate($img, 37, 99, 235));
    ob_start();
    imagepng($img);
    Storage::disk('public')->put("ojt-photos/{$intern->id}/duty.png", ob_get_clean());
    $weekLog1->update(['photo_path' => "ojt-photos/{$intern->id}/duty.png", 'notes' => 'Encoded records.']);
    $weekLog2->update(['notes' => 'Fixed the office printer.']);
    $outsideLog->update(['notes' => 'Network maintenance.']);

    return compact('admin', 'intern', 'weekLog1', 'weekLog2', 'outsideLog');
}

/** The PDF's page count — journals compile one page per duty day. */
function pdfPageCount(string $binary): int
{
    preg_match('/\/Type\s*\/Pages.*?\/Count\s+(\d+)/s', $binary, $matches);

    return (int) ($matches[1] ?? 0);
}

test('daily export returns the single day as a PDF', function () {
    ['admin' => $admin, 'intern' => $intern] = journalScenario();

    $response = $this->actingAs($admin)
        ->get(route('admin.interns.journals.export', ['intern' => $intern, 'type' => 'daily', 'date' => '2026-09-08']));

    $response->assertOk()
        ->assertHeader('Content-Type', 'application/pdf');

    expect($response->getContent())->toStartWith('%PDF')
        ->and($response->headers->get('Content-Disposition'))->toContain('Journals_Exporta_Nida_2026-09-08.pdf')
        // One duty day → one page, and the journal's photo is embedded.
        ->and(pdfPageCount($response->getContent()))->toBe(1)
        ->and($response->getContent())->toContain('/Image');
});

test('weekly export returns the Monday-Sunday week of the picked day', function () {
    ['admin' => $admin, 'intern' => $intern] = journalScenario();

    // Sep 11 2026 is a Friday — its week covers Sep 8 and Sep 10, not Sep 21.
    $response = $this->actingAs($admin)
        ->get(route('admin.interns.journals.export', ['intern' => $intern, 'type' => 'weekly', 'week_of' => '2026-09-11']));

    $response->assertOk();

    expect($response->getContent())->toStartWith('%PDF')
        ->and($response->headers->get('Content-Disposition'))->toContain('week-of-2026-09-07')
        ->and(pdfPageCount($response->getContent()))->toBe(2);
});

test('range export returns the inclusive window only', function () {
    ['admin' => $admin, 'intern' => $intern] = journalScenario();

    // Sep 9 → Sep 21 captures two duty days; Sep 8 stays out.
    $response = $this->actingAs($admin)
        ->get(route('admin.interns.journals.export', ['intern' => $intern, 'type' => 'range', 'from' => '2026-09-09', 'to' => '2026-09-21']));

    $response->assertOk();

    expect(pdfPageCount($response->getContent()))->toBe(2);
});

test('all export compiles every journal on record', function () {
    ['admin' => $admin, 'intern' => $intern] = journalScenario();

    $response = $this->actingAs($admin)
        ->get(route('admin.interns.journals.export', ['intern' => $intern, 'type' => 'all']));

    $response->assertOk();

    expect($response->getContent())->toStartWith('%PDF')
        ->and(pdfPageCount($response->getContent()))->toBe(3);
});

test('an empty selection redirects back with a notice instead of a blank PDF', function () {
    ['admin' => $admin, 'intern' => $intern] = journalScenario();

    $this->actingAs($admin)
        ->get(route('admin.interns.journals.export', ['intern' => $intern, 'type' => 'daily', 'date' => '2026-09-20']))
        ->assertRedirect()
        ->assertSessionHas('status', 'No duty journals found for that selection.');
});

test('the export route is admin-only and interns cannot use it', function () {
    ['intern' => $intern] = journalScenario();

    $this->actingAs($intern)
        ->get(route('admin.interns.journals.export', ['intern' => $intern, 'type' => 'all']))
        ->assertForbidden();
});

test('validation rejects malformed selections', function () {
    ['admin' => $admin, 'intern' => $intern] = journalScenario();

    // Daily without a date.
    $this->actingAs($admin)
        ->get(route('admin.interns.journals.export', ['intern' => $intern, 'type' => 'daily']))
        ->assertSessionHasErrors('date');

    // Range with to before from.
    $this->actingAs($admin)
        ->get(route('admin.interns.journals.export', ['intern' => $intern, 'type' => 'range', 'from' => '2026-09-20', 'to' => '2026-09-10']))
        ->assertSessionHasErrors('to');
});
