<?php

use App\Models\OjtEnrollment;
use App\Models\OjtLog;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

uses(RefreshDatabase::class);

function makeIntern(array $attributes = []): User
{
    return User::create([
        'role' => 'intern',
        'ojt_track' => 'internship',
        'ojt_status' => 'active',
        'target_hours' => 500,
        'first_name' => 'Test',
        'last_name' => 'Intern',
        'email' => fake()->unique()->safeEmail(),
        'student_id' => 'T-1001',
        'password' => 'password',
        // Treated as "already picked their own password" so the rest of
        // the suite skips the forced-change redirect; pass null explicitly
        // to test that flow (see LoginSecurityTest).
        'password_changed_at' => now(),
        // Default to a completed profile so the dashboard is reachable;
        // the profile-completion suite passes null explicitly.
        'profile_completed_at' => now(),
        'is_active' => true,
        ...$attributes,
    ]);
}

/**
 * Like makeIntern() but with a random unique student_id, for suites that
 * spin up several interns at once (timesheet/transfer helpers). Global Pest
 * helper shared across this suite and TransferOjtLogsDateTest.
 */
function makeTimesheetIntern(array $attributes = []): User
{
    return User::create([
        'role' => 'intern',
        'ojt_track' => 'internship',
        'ojt_status' => 'active',
        'target_hours' => 500,
        'first_name' => 'Test',
        'last_name' => 'Intern',
        'email' => fake()->unique()->safeEmail(),
        'student_id' => fake()->unique()->numerify('T-####'),
        'password' => 'password',
        'password_changed_at' => now(),
        'profile_completed_at' => now(),
        'is_active' => true,
        ...$attributes,
    ]);
}

function makeActiveEnrollment(User $intern): OjtEnrollment
{
    return OjtEnrollment::create([
        'user_id' => $intern->id,
        'label' => 'Internship OJT',
        'target_hours' => 500,
        'status' => 'active',
        'started_at' => today()->toDateString(),
    ]);
}

function makeLog(User $intern, OjtEnrollment $enrollment, ?string $date = null, array $times = []): OjtLog
{
    return OjtLog::create([
        'user_id' => $intern->id,
        'ojt_enrollment_id' => $enrollment->id,
        'date' => $date ?? today()->toDateString(),
        ...$times,
    ]);
}

function fullDutyTimes(): array
{
    return [
        'am_time_in' => '08:00',
        'am_time_out' => '12:00',
        'pm_time_in' => '13:00',
        'pm_time_out' => '17:00',
    ];
}

beforeEach(function () {
    Storage::fake('public');
});

test('intern can upload their proof photo and notes after clocking out', function () {
    $intern = makeIntern();
    $enrollment = makeActiveEnrollment($intern);
    $log = makeLog($intern, $enrollment, times: fullDutyTimes());

    $this->actingAs($intern)
        ->post(route('intern.photo.store', $log), [
            'photo' => UploadedFile::fake()->image('duty.jpg'),
            'notes' => 'Sorted the filing room.',
        ])
        ->assertSessionHas('status');

    $log->refresh();

    expect($log->photo_path)->not->toBeNull();
    expect($log->notes)->toBe('Sorted the filing room.');
    expect($log->photo_required)->toBeFalse();

    Storage::disk('public')->assertExists($log->photo_path);
});

test('intern can upload a proof photo for a past day still missing it', function () {
    $intern = makeIntern();
    $enrollment = makeActiveEnrollment($intern);
    $log = makeLog($intern, $enrollment, date: today()->subDay()->toDateString(), times: fullDutyTimes());

    expect($log->photo_required)->toBeTrue();

    $this->actingAs($intern)
        ->post(route('intern.photo.store', $log), [
            'photo' => UploadedFile::fake()->image('past-day.jpg'),
            'notes' => 'Caught up the entry I missed.',
        ])
        ->assertSessionHas('status');

    expect($log->refresh()->photo_path)->not->toBeNull();
    expect($log->photo_required)->toBeFalse();

    Storage::disk('public')->assertExists($log->photo_path);
});

test('upload is blocked before any clock-out is recorded', function () {
    $intern = makeIntern();
    $enrollment = makeActiveEnrollment($intern);
    $log = makeLog($intern, $enrollment, times: ['am_time_in' => '08:00', 'pm_time_in' => '13:00']);

    $this->actingAs($intern)
        ->post(route('intern.photo.store', $log), [
            'photo' => UploadedFile::fake()->image('duty.jpg'),
        ])
        ->assertStatus(422);
});

test('intern cannot upload to another intern\'s log entry', function () {
    $other = makeIntern();
    $otherEnrollment = makeActiveEnrollment($other);
    $otherLog = makeLog($other, $otherEnrollment, times: fullDutyTimes());

    $intern = makeIntern(['student_id' => 'T-1002']);

    $this->actingAs($intern)
        ->post(route('intern.photo.store', $otherLog), [
            'photo' => UploadedFile::fake()->image('duty.jpg'),
        ])
        ->assertStatus(404);
});

test('intern cannot upload to an entry from a completed OJT set', function () {
    $intern = makeIntern();
    $oldEnrollment = makeActiveEnrollment($intern);
    $oldLog = makeLog($intern, $oldEnrollment, times: fullDutyTimes());

    // A newer set supersedes the old one — it becomes the "current" set.
    $completedEnrollment = OjtEnrollment::create([
        'user_id' => $intern->id,
        'label' => 'Internship OJT - Section B',
        'target_hours' => 500,
        'status' => 'completed',
        'started_at' => today()->addWeek()->toDateString(),
    ]);

    expect($intern->currentEnrollment->id)->toBe($completedEnrollment->id);

    $this->actingAs($intern)
        ->post(route('intern.photo.store', $oldLog), [
            'photo' => UploadedFile::fake()->image('duty.jpg'),
        ])
        ->assertStatus(422);
});

test('both a photo and a journal message are required', function () {
    $intern = makeIntern();
    $enrollment = makeActiveEnrollment($intern);
    $log = makeLog($intern, $enrollment, times: [
        'am_time_in' => '08:00',
        'am_time_out' => '12:00',
    ]);

    // A message without a photo is rejected.
    $this->actingAs($intern)
        ->post(route('intern.photo.store', $log), ['notes' => 'no photo here'])
        ->assertSessionHasErrors('photo');

    // A photo without a message is rejected.
    $this->actingAs($intern)
        ->post(route('intern.photo.store', $log), ['photo' => UploadedFile::fake()->image('duty.jpg')])
        ->assertSessionHasErrors('notes');
});

test('re-uploading keeps a single file per day (replaces in place)', function () {
    $intern = makeIntern();
    $enrollment = makeActiveEnrollment($intern);
    $log = makeLog($intern, $enrollment, times: [
        'am_time_in' => '08:00',
        'am_time_out' => '12:00',
    ]);

    $this->actingAs($intern)->post(route('intern.photo.store', $log), ['photo' => UploadedFile::fake()->image('one.jpg'), 'notes' => 'First try.']);
    $first = $log->refresh()->photo_path;

    $this->actingAs($intern)->post(route('intern.photo.store', $log), ['photo' => UploadedFile::fake()->image('two.jpg'), 'notes' => 'Replaced it.']);
    $second = $log->refresh()->photo_path;

    // Deterministic lastname_firstname_date naming: the same day re-uses
    // the same path, so the newest upload is the only file on disk.
    expect($first)->not->toBeNull()
        ->and($second)->toBe($first);
    Storage::disk('public')->assertExists($second);
});

test('intern can edit their journal message without re-uploading the photo', function () {
    $intern = makeIntern();
    $enrollment = makeActiveEnrollment($intern);
    $log = makeLog($intern, $enrollment, times: fullDutyTimes());

    $this->actingAs($intern)->post(route('intern.photo.store', $log), [
        'photo' => UploadedFile::fake()->image('duty.jpg'),
        'notes' => 'Original notes.',
    ]);

    $path = $log->refresh()->photo_path;

    $this->actingAs($intern)->post(route('intern.photo.store', $log), [
        'notes' => 'Corrected notes.',
    ])->assertSessionHas('status');

    $log->refresh();

    expect($log->notes)->toBe('Corrected notes.');
    expect($log->photo_path)->toBe($path);
    Storage::disk('public')->assertExists($path);
});

test('removing a journal soft-deletes it (photo kept on disk) and the day can be redone', function () {
    $intern = makeIntern();
    $enrollment = makeActiveEnrollment($intern);
    $log = makeLog($intern, $enrollment, times: fullDutyTimes());

    $this->actingAs($intern)->post(route('intern.photo.store', $log), [
        'photo' => UploadedFile::fake()->image('duty.jpg'),
        'notes' => 'To be removed.',
    ]);

    $path = $log->refresh()->photo_path;

    $this->actingAs($intern)->delete(route('intern.photo.destroy', $log))
        ->assertSessionHas('status');

    $log->refresh();

    // Soft delete: the journal is hidden but the photo and notes are kept
    // on disk so the removal can be undone.
    expect($log->journal_removed_at)->not->toBeNull();
    expect($log->journal_removed)->toBeTrue();
    expect($log->has_journal)->toBeFalse();
    expect($log->photo_path)->not->toBeNull();
    expect($log->notes)->not->toBeNull();
    expect($log->photo_required)->toBeTrue(); // clocked out, so a journal is required again

    Storage::disk('public')->assertExists($path);

    // The day flips back to "awaiting journal" — a fresh upload works and
    // clears the removed flag.
    $this->actingAs($intern)->post(route('intern.photo.store', $log), [
        'photo' => UploadedFile::fake()->image('again.jpg'),
        'notes' => 'Redone properly.',
    ])->assertSessionHas('status');

    $log->refresh();

    expect($log->photo_path)->not->toBeNull();
    expect($log->journal_removed_at)->toBeNull();
    expect($log->has_journal)->toBeTrue();
});

test('intern can undo a removed journal, restoring the photo and notes', function () {
    $intern = makeIntern();
    $enrollment = makeActiveEnrollment($intern);
    $log = makeLog($intern, $enrollment, times: fullDutyTimes());

    $this->actingAs($intern)->post(route('intern.photo.store', $log), [
        'photo' => UploadedFile::fake()->image('duty.jpg'),
        'notes' => 'Keep this.',
    ]);

    $path = $log->refresh()->photo_path;

    $this->actingAs($intern)->delete(route('intern.photo.destroy', $log));
    expect($log->refresh()->journal_removed_at)->not->toBeNull();

    $this->actingAs($intern)
        ->post(route('intern.photo.restore', $log))
        ->assertSessionHas('status');

    $log->refresh();

    expect($log->journal_removed_at)->toBeNull();
    expect($log->journal_removed)->toBeFalse();
    expect($log->has_journal)->toBeTrue();
    expect($log->photo_path)->toBe($path);
    expect($log->notes)->toBe('Keep this.');
    Storage::disk('public')->assertExists($path);
});

test('intern cannot restore another intern\'s journal', function () {
    $other = makeIntern();
    $otherEnrollment = makeActiveEnrollment($other);
    $otherLog = makeLog($other, $otherEnrollment, times: fullDutyTimes());

    $this->actingAs($other)->post(route('intern.photo.store', $otherLog), [
        'photo' => UploadedFile::fake()->image('duty.jpg'),
        'notes' => 'Mine.',
    ]);
    $this->actingAs($other)->delete(route('intern.photo.destroy', $otherLog));

    $intern = makeIntern(['student_id' => 'T-1002']);

    $this->actingAs($intern)
        ->post(route('intern.photo.restore', $otherLog))
        ->assertStatus(404);
});

test('intern cannot restore a journal from a completed OJT set', function () {
    $intern = makeIntern();
    $oldEnrollment = makeActiveEnrollment($intern);
    $oldLog = makeLog($intern, $oldEnrollment, times: fullDutyTimes());

    $this->actingAs($intern)->post(route('intern.photo.store', $oldLog), [
        'photo' => UploadedFile::fake()->image('duty.jpg'),
        'notes' => 'Frozen.',
    ]);
    $this->actingAs($intern)->delete(route('intern.photo.destroy', $oldLog));

    // A newer set supersedes the old one — it becomes the "current" set.
    // (The requests above cached `currentEnrollment` on the intern
    // instance, so drop the relation to force a fresh query.)
    OjtEnrollment::create([
        'user_id' => $intern->id,
        'label' => 'Internship OJT - Section B',
        'target_hours' => 500,
        'status' => 'completed',
        'started_at' => today()->addWeek()->toDateString(),
    ]);

    $intern->unsetRelation('currentEnrollment');

    $this->actingAs($intern)
        ->post(route('intern.photo.restore', $oldLog))
        ->assertStatus(422);
});

test('a journal cannot be restored when none was removed', function () {
    $intern = makeIntern();
    $enrollment = makeActiveEnrollment($intern);
    $log = makeLog($intern, $enrollment, times: fullDutyTimes());

    $this->actingAs($intern)
        ->post(route('intern.photo.restore', $log))
        ->assertStatus(422);
});

test('intern cannot remove another intern\'s journal', function () {
    $other = makeIntern();
    $otherEnrollment = makeActiveEnrollment($other);
    $otherLog = makeLog($other, $otherEnrollment, times: fullDutyTimes());

    $this->actingAs($other)->post(route('intern.photo.store', $otherLog), [
        'photo' => UploadedFile::fake()->image('duty.jpg'),
        'notes' => 'Mine.',
    ]);

    $intern = makeIntern(['student_id' => 'T-1002']);

    $this->actingAs($intern)
        ->delete(route('intern.photo.destroy', $otherLog))
        ->assertStatus(404);
});

test('intern cannot remove a journal from a completed OJT set', function () {
    $intern = makeIntern();
    $oldEnrollment = makeActiveEnrollment($intern);
    $oldLog = makeLog($intern, $oldEnrollment, times: fullDutyTimes());

    $this->actingAs($intern)->post(route('intern.photo.store', $oldLog), [
        'photo' => UploadedFile::fake()->image('duty.jpg'),
        'notes' => 'Frozen.',
    ]);

    // A newer set supersedes the old one — it becomes the "current" set.
    // (The upload request above cached `currentEnrollment` on the intern
    // instance, so drop the relation to force a fresh query.)
    OjtEnrollment::create([
        'user_id' => $intern->id,
        'label' => 'Internship OJT - Section B',
        'target_hours' => 500,
        'status' => 'completed',
        'started_at' => today()->addWeek()->toDateString(),
    ]);

    $intern->unsetRelation('currentEnrollment');

    $this->actingAs($intern)
        ->delete(route('intern.photo.destroy', $oldLog))
        ->assertStatus(422);
});

test('a journal cannot be removed when none exists', function () {
    $intern = makeIntern();
    $enrollment = makeActiveEnrollment($intern);
    $log = makeLog($intern, $enrollment, times: fullDutyTimes());

    $this->actingAs($intern)
        ->delete(route('intern.photo.destroy', $log))
        ->assertStatus(422);
});

test('intern can download their daily report as a PDF with the photo embedded', function () {
    $intern = makeIntern();
    $enrollment = makeActiveEnrollment($intern);
    $log = makeLog($intern, $enrollment, times: fullDutyTimes());

    $this->actingAs($intern)->post(route('intern.photo.store', $log), [
        'photo' => UploadedFile::fake()->image('duty.jpg'),
        'notes' => 'Compiled the inventory list.',
    ]);

    $filename = 'DailyReport_'.$intern->student_id.'_'.today()->format('Y-m-d').'.pdf';

    $response = $this->actingAs($intern)->get(route('intern.report.show', $log));

    $response->assertOk();
    $response->assertHeader('Content-Type', 'application/pdf');
    $response->assertDownload($filename);

    $content = $response->getContent();
    expect($content)->toStartWith('%PDF');
    expect($content)->toContain('/Image'); // the proof photo is embedded, not hot-linked
});

test('intern cannot view another intern\'s daily report', function () {
    $other = makeIntern();
    $otherEnrollment = makeActiveEnrollment($other);
    $otherLog = makeLog($other, $otherEnrollment, times: fullDutyTimes());

    $intern = makeIntern(['student_id' => 'T-1002']);

    $this->actingAs($intern)
        ->get(route('intern.report.show', $otherLog))
        ->assertStatus(404);
});
