<?php

use App\Models\OjtEnrollment;
use App\Models\OjtLog;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

/*
 * `ojt:transfer-logs {from} {to}` moves entries onto a different calendar
 * date. It rewrites only the date (times/hours untouched) and never
 * overwrites an intern who already has an entry on the destination date,
 * since ojt_logs is unique on (user_id, date).
 *
 * Reuses makeTimesheetIntern(), makeLog() and fullDutyTimes()
 * (InternPhotoUploadTest) — global Pest helpers in this suite.
 */

function enrollForTransfer(User $intern): OjtEnrollment
{
    return OjtEnrollment::create([
        'user_id' => $intern->id,
        'label' => 'Internship OJT',
        'target_hours' => 500,
        'status' => 'active',
        'started_at' => today()->toDateString(),
    ]);
}

it('moves a log to the destination date when that date is free', function () {
    $intern = makeTimesheetIntern();
    $log = makeLog($intern, enrollForTransfer($intern), '2026-08-17', fullDutyTimes());
    $hoursBefore = (float) $log->hours_rendered;

    $this->artisan('ojt:transfer-logs', ['from' => '2026-08-17', 'to' => '2026-08-18'])
        ->assertSuccessful();

    $log->refresh();

    expect($log->date->toDateString())->toBe('2026-08-18')
        ->and((float) $log->hours_rendered)->toBe($hoursBefore) // times/hours untouched
        ->and(OjtLog::whereDate('date', '2026-08-17')->count())->toBe(0);
});

it('skips a log when the intern already has an entry on the destination date', function () {
    $intern = makeTimesheetIntern();
    $enrollment = enrollForTransfer($intern);
    $source = makeLog($intern, $enrollment, '2026-08-17', fullDutyTimes());
    $existing = makeLog($intern, $enrollment, '2026-08-18', [
        'am_time_in' => '08:00',
        'am_time_out' => '10:00',
    ]);

    $this->artisan('ojt:transfer-logs', ['from' => '2026-08-17', 'to' => '2026-08-18'])
        ->assertSuccessful();

    // Neither row is moved or overwritten — both survive on their own dates.
    expect($source->refresh()->date->toDateString())->toBe('2026-08-17')
        ->and($existing->refresh()->date->toDateString())->toBe('2026-08-18')
        ->and(OjtLog::count())->toBe(2);
});

it('writes nothing on a dry run', function () {
    $intern = makeTimesheetIntern();
    $log = makeLog($intern, enrollForTransfer($intern), '2026-08-17', fullDutyTimes());

    $this->artisan('ojt:transfer-logs', [
        'from' => '2026-08-17',
        'to' => '2026-08-18',
        '--dry-run' => true,
    ])->assertSuccessful();

    expect($log->refresh()->date->toDateString())->toBe('2026-08-17');
});

it('only moves the targeted intern when --student is given', function () {
    $alice = makeTimesheetIntern(['student_id' => 'T-1001']);
    $bob = makeTimesheetIntern(['student_id' => 'T-1002']);
    $aliceLog = makeLog($alice, enrollForTransfer($alice), '2026-08-17', fullDutyTimes());
    $bobLog = makeLog($bob, enrollForTransfer($bob), '2026-08-17', fullDutyTimes());

    $this->artisan('ojt:transfer-logs', [
        'from' => '2026-08-17',
        'to' => '2026-08-18',
        '--student' => 'T-1001',
    ])->assertSuccessful();

    expect($aliceLog->refresh()->date->toDateString())->toBe('2026-08-18')
        ->and($bobLog->refresh()->date->toDateString())->toBe('2026-08-17');
});

it('fails when the two dates are the same', function () {
    $this->artisan('ojt:transfer-logs', ['from' => '2026-08-17', 'to' => '2026-08-17'])
        ->assertFailed();
});
