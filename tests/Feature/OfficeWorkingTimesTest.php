<?php

use App\Models\Office;
use App\Models\OjtLog;
use App\Models\User;
use App\Support\AttendanceRecorder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

/*
 * Per-office working times. An office may run its own schedule (Offices →
 * Working Times); its interns' hours and the desk scanner's slot boundaries
 * then follow the office instead of the campus-wide Settings. Offices that
 * leave the times blank keep the campus schedule.
 *
 * Uses the intern/enrollment/log helpers from InternPhotoUploadTest.
 */

function officeHoursIntern(Office $office, array $attributes = []): User
{
    return makeIntern([
        'office_id' => $office->id,
        'student_id' => fake()->unique()->numerify('T-####'),
        ...$attributes,
    ]);
}

function earlyShiftOffice(): Office
{
    return Office::create([
        'name' => 'Early Shift Office',
        'type' => 'internal',
        'am_time_in' => '07:00',
        'am_time_out' => '11:00',
        'pm_time_in' => '13:00',
        'pm_time_out' => '17:00',
    ]);
}

test('an office with its own working times is measured against them', function () {
    $intern = officeHoursIntern(earlyShiftOffice());
    $enrollment = makeActiveEnrollment($intern);

    // 7–11 is inside this office's morning window — all regular. Against
    // the campus schedule (8–12) the first hour would be overtime.
    $log = makeLog($intern, $enrollment, times: ['am_time_in' => '07:00', 'am_time_out' => '11:00']);

    expect((float) $log->regular_hours)->toBe(4.0)
        ->and((float) $log->overtime_hours)->toBe(0.0);
});

test('an office without its own times falls back to the campus schedule', function () {
    $office = Office::create(['name' => 'Default Hours Office', 'type' => 'internal']);
    $intern = officeHoursIntern($office);
    $enrollment = makeActiveEnrollment($intern);

    // 7–12 against the campus 8–12 window: the early hour is overtime.
    $log = makeLog($intern, $enrollment, times: ['am_time_in' => '07:00', 'am_time_out' => '12:00']);

    expect((float) $log->regular_hours)->toBe(4.0)
        ->and((float) $log->overtime_hours)->toBe(1.0);
});

test('one office\'s custom times do not leak into other offices', function () {
    $early = earlyShiftOffice();
    $standard = Office::create(['name' => 'Standard Office', 'type' => 'internal']);

    $earlyIntern = officeHoursIntern($early);
    $standardIntern = officeHoursIntern($standard);

    // Same worked span, different schedules: the early office counts it
    // all regular, the standard office sees an early-arrival overtime hour.
    $earlyLog = makeLog($earlyIntern, makeActiveEnrollment($earlyIntern), times: [
        'am_time_in' => '07:00', 'am_time_out' => '11:00',
    ]);
    $standardLog = makeLog($standardIntern, makeActiveEnrollment($standardIntern), times: [
        'am_time_in' => '07:00', 'am_time_out' => '11:00',
    ]);

    expect((float) $earlyLog->regular_hours)->toBe(4.0)
        ->and((float) $earlyLog->overtime_hours)->toBe(0.0)
        ->and((float) $standardLog->regular_hours)->toBe(3.0)
        ->and((float) $standardLog->overtime_hours)->toBe(1.0);
});

test('the desk scanner opens the afternoon after the office\'s own morning end', function () {
    $intern = officeHoursIntern(earlyShiftOffice());
    $enrollment = makeActiveEnrollment($intern);

    // 11:30 is still morning on the campus schedule (AM ends at noon),
    // but this office's morning ends at 11:00 — the scan opens the PM session.
    $result = AttendanceRecorder::record($intern, $enrollment, today()->setTime(11, 30));

    expect($result['state'])->toBe('recorded')
        ->and($result['slot'])->toBe('pm_time_in');
});

test('the desk scanner keeps the campus morning end for offices without custom times', function () {
    $office = Office::create(['name' => 'Default Hours Office', 'type' => 'internal']);
    $intern = officeHoursIntern($office);
    $enrollment = makeActiveEnrollment($intern);

    $result = AttendanceRecorder::record($intern, $enrollment, today()->setTime(11, 30));

    expect($result['state'])->toBe('recorded')
        ->and($result['slot'])->toBe('am_time_in');
});
