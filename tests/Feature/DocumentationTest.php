<?php

use App\Models\DocumentTemplate;
use App\Models\OjtLog;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

uses(RefreshDatabase::class);

/*
 * The intern's documentation archive: photos saved with the
 * lastname_firstname_mm_dd_yyyy naming scheme, browsable in a gallery.
 */

beforeEach(function () {
    Storage::fake('public');
});

test('uploaded duty photos follow the lastname_firstname_date naming scheme', function () {
    $intern = makeIntern(['first_name' => 'Jayson P', 'last_name' => 'Francisco']);
    $enrollment = makeActiveEnrollment($intern);
    $log = makeLog($intern, $enrollment, date: '2026-08-22', times: fullDutyTimes());

    $this->actingAs($intern)
        ->post(route('intern.photo.store', $log), [
            'photo' => UploadedFile::fake()->image('proof.jpg'),
            'notes' => 'Duty for the day.',
        ])
        ->assertSessionHas('status');

    $log->refresh();

    expect($log->photo_path)->toBe('ojt-photos/'.$intern->id.'/francisco_jaysonp_08_22_2026.jpg');
    Storage::disk('public')->assertExists($log->photo_path);
});

test('re-uploading on the same day replaces the file in place', function () {
    $intern = makeIntern();
    $enrollment = makeActiveEnrollment($intern);
    $log = makeLog($intern, $enrollment, times: fullDutyTimes());

    $this->actingAs($intern)->post(route('intern.photo.store', $log), ['photo' => UploadedFile::fake()->image('one.jpg'), 'notes' => 'First upload.']);
    $path = $log->refresh()->photo_path;

    $this->actingAs($intern)->post(route('intern.photo.store', $log), ['photo' => UploadedFile::fake()->image('two.png'), 'notes' => 'Second upload.']);

    // Same day + same intern → same deterministic name (stem), fresh
    // extension, and exactly one file on disk.
    $newPath = $log->refresh()->photo_path;

    expect(dirname($newPath).'/'.pathinfo($newPath, PATHINFO_FILENAME))->toBe(dirname($path).'/'.pathinfo($path, PATHINFO_FILENAME))
        ->and(pathinfo($newPath, PATHINFO_EXTENSION))->toBe('png');
    Storage::disk('public')->assertExists($newPath);
    Storage::disk('public')->assertMissing($path);
});

test('the documentation gallery shows the interns own photos', function () {
    $intern = makeIntern();
    $enrollment = makeActiveEnrollment($intern);
    $withPhoto = makeLog($intern, $enrollment, times: fullDutyTimes());
    $this->actingAs($intern)->post(route('intern.photo.store', $withPhoto), ['photo' => UploadedFile::fake()->image('mine.jpg'), 'notes' => 'My duty photo.']);

    // Another intern's photo must not appear
    $other = makeIntern(['student_id' => 'T-1002']);
    $otherEnrollment = makeActiveEnrollment($other);
    $otherLog = makeLog($other, $otherEnrollment, times: fullDutyTimes());
    $this->actingAs($other)->post(route('intern.photo.store', $otherLog), ['photo' => UploadedFile::fake()->image('theirs.jpg'), 'notes' => 'Their duty photo.']);

    $this->actingAs($intern)->get(route('intern.documentation'))
        ->assertOk()
        ->assertSee($withPhoto->refresh()->photo_url)
        ->assertDontSee($otherLog->refresh()->photo_url);
});

test('the documentation gallery offers a remove control only for current-set journals', function () {
    $intern = makeIntern();

    // A journal from the first set, later superseded by a newer one — it
    // becomes read-only history, so it must not offer the remove control.
    $firstEnrollment = makeActiveEnrollment($intern);
    $pastLog = makeLog($intern, $firstEnrollment, date: today()->subDay()->toDateString(), times: fullDutyTimes());
    $this->actingAs($intern)->post(route('intern.photo.store', $pastLog), ['photo' => UploadedFile::fake()->image('past.jpg'), 'notes' => 'Past duty.']);

    // A second set supersedes the first — it becomes the current set. The
    // upload request above cached `currentEnrollment` on the intern instance,
    // so drop the relation to force a fresh query before the next upload.
    $secondEnrollment = makeActiveEnrollment($intern);
    $intern->unsetRelation('currentEnrollment');
    $currentLog = makeLog($intern, $secondEnrollment, times: fullDutyTimes());
    $this->actingAs($intern)->post(route('intern.photo.store', $currentLog), ['photo' => UploadedFile::fake()->image('current.jpg'), 'notes' => 'Current duty.']);

    $this->actingAs($intern)->get(route('intern.documentation'))
        ->assertOk()
        ->assertSee(route('intern.photo.destroy', $currentLog))
        ->assertDontSee(route('intern.photo.destroy', $pastLog));
});

test('archiving a journal files it under Archive until restored', function () {
    $intern = makeIntern();
    $enrollment = makeActiveEnrollment($intern);
    $log = makeLog($intern, $enrollment, times: fullDutyTimes());

    $this->actingAs($intern)->post(route('intern.photo.store', $log), [
        'photo' => UploadedFile::fake()->image('duty.jpg'),
        'notes' => 'My duty photo.',
    ]);

    $this->actingAs($intern)->delete(route('intern.photo.destroy', $log));

    // Archived: the day's card drops to the placeholder, and the journal
    // moves into the Archive section (photo kept on disk, restorable).
    $response = $this->actingAs($intern)->get(route('intern.documentation'));
    $response->assertOk()
        ->assertSee('Archived journals')
        ->assertSee($log->refresh()->photo_url);
    Storage::disk('public')->assertExists($log->photo_path);

    // The archive offers Restore (current set) and the permanent option.
    $response->assertSee(route('intern.photo.restore', $log))
        ->assertSee(route('intern.photo.force-destroy', $log));

    // Restoring brings the journal straight back to the main gallery and
    // empties the archive.
    $this->actingAs($intern)->post(route('intern.photo.restore', $log));

    $this->actingAs($intern)->get(route('intern.documentation'))
        ->assertOk()
        ->assertSee($log->refresh()->photo_url)
        ->assertDontSee('Archived journals');
});

test('delete forever permanently erases the journal but keeps the duty day', function () {
    $intern = makeIntern();
    $enrollment = makeActiveEnrollment($intern);
    $log = makeLog($intern, $enrollment, times: fullDutyTimes());

    $this->actingAs($intern)->post(route('intern.photo.store', $log), [
        'photo' => UploadedFile::fake()->image('duty.jpg'),
        'notes' => 'Gone soon.',
    ]);
    $path = $log->refresh()->photo_path;

    $this->actingAs($intern)->delete(route('intern.photo.force-destroy', $log))
        ->assertRedirect()
        ->assertSessionHas('status');

    $log->refresh();

    // Photo erased from disk, journal message gone, day back to awaiting —
    // and nothing left to restore.
    Storage::disk('public')->assertMissing($path);
    expect($log->photo_path)->toBeNull()
        ->and($log->notes)->toBeNull()
        ->and($log->journal_removed_at)->toBeNull()
        // The duty times remain untouched.
        ->and(substr((string) $log->am_time_in, 0, 5))->toBe('08:00');

    $this->actingAs($intern)->get(route('intern.documentation'))
        ->assertOk()
        ->assertDontSee('Archived journals');
});

test('archived journals from completed sets are read-only', function () {
    $intern = makeIntern();

    // A journal from an earlier set, later superseded by a newer set.
    $firstEnrollment = makeActiveEnrollment($intern);
    $pastLog = makeLog($intern, $firstEnrollment, date: today()->subDay()->toDateString(), times: fullDutyTimes());
    $this->actingAs($intern)->post(route('intern.photo.store', $pastLog), [
        'photo' => UploadedFile::fake()->image('past.jpg'),
        'notes' => 'Past duty.',
    ]);
    $pastLog->update(['journal_removed_at' => now()]);

    $secondEnrollment = makeActiveEnrollment($intern);
    $intern->unsetRelation('currentEnrollment');
    makeLog($intern, $secondEnrollment, times: fullDutyTimes());

    // The archived past-set journal is listed, but only read-only — no
    // restore and no permanent delete for frozen history.
    $response = $this->actingAs($intern)->get(route('intern.documentation'));
    $response->assertOk()
        ->assertSee('Past duty.')
        ->assertDontSee(route('intern.photo.restore', $pastLog))
        ->assertDontSee(route('intern.photo.force-destroy', $pastLog));
});

test('documentation page is intern-only', function () {
    // Guest first — actingAs() below would otherwise leak into this check.
    $this->get(route('intern.documentation'))->assertRedirect(route('login'));

    $admin = makeStaff(['role' => 'admin']);
    $this->actingAs($admin)->get(route('intern.documentation'))->assertForbidden();
});

test('the intern can export their journals as the Word Weekly Progress Report', function () {
    // The uploaded template lives on the local disk; isolate it here too.
    Storage::fake('local');

    $intern = makeIntern();
    $enrollment = makeActiveEnrollment($intern);
    $log = makeLog($intern, $enrollment, times: fullDutyTimes());
    $this->actingAs($intern)->post(route('intern.photo.store', $log), [
        'photo' => UploadedFile::fake()->image('duty.jpg'),
        'notes' => 'My first journal entry.',
    ]);

    activateTemplate(DocumentTemplate::TYPE_WEEKLY_PROGRESS_REPORT);

    $response = $this->actingAs($intern)->get(route('intern.journals.export'));

    // The journals export is the school's Word form — the admin's uploaded
    // Weekly Progress Report template filled with the journal entries —
    // not a generated PDF. It always downloads as
    // "MyJournal_Lastname_Firstname_year.docx".
    $response->assertOk()
        ->assertHeader('Content-Type', 'application/vnd.openxmlformats-officedocument.wordprocessingml.document');

    expect($response->getContent())->toStartWith('PK')           // a real .docx is a zip
        ->and($response->headers->get('Content-Disposition'))->toContain('MyJournal_Intern_Test_2026.docx');
});

test('the My Journal page offers Export Journal but no Time Frame button', function () {
    $intern = makeIntern();
    $enrollment = makeActiveEnrollment($intern);
    makeLog($intern, $enrollment, times: fullDutyTimes());

    $this->actingAs($intern)->get(route('intern.documentation'))
        ->assertOk()
        // The journals export stays on the page.
        ->assertSee(route('intern.journals.export'))
        // The Time Frame download moved to its own My Time Frame page.
        ->assertDontSee(route('intern.timesheet'));
});

test('exporting with no journals on record redirects back with a notice', function () {
    $intern = makeIntern();

    $this->actingAs($intern)->get(route('intern.journals.export'))
        ->assertRedirect(route('intern.documentation'))
        ->assertSessionHas('status', "You don't have any journals on record yet — upload your first one from this page.");
});
