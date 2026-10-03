<?php

use App\Models\OjtLog;
use App\Services\TimesheetReportService;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

/*
 * The Timesheet's per-day rows — specifically the "Time In–Out" cell — for
 * days that include a stepped-out-and-returned episode. The school's sheet
 * has one row per day, so a morning excursion can't get its own row: the
 * row's Time In–Out cell joins every worked interval of the day in order
 * with " / ". Asserts TimesheetReportService::rows() directly (same pattern
 * as TimesheetHoursFormatTest) rather than through the generated Word file.
 */

/*
 * RefreshDatabase per test; the intern / enrollment / log helpers come from
 * InternPhotoUploadTest (loaded with the full suite).
 */

function timesheetRowFor(array $times): OjtLog
{
    $intern = makeIntern();
    $enrollment = makeActiveEnrollment($intern);

    return makeLog($intern, $enrollment, date: '2026-09-15', times: $times);
}

test('a normal day prints the classic two intervals', function () {
    $log = timesheetRowFor([
        'am_time_in' => '08:00',
        'am_time_out' => '12:00',
        'pm_time_in' => '13:00',
        'pm_time_out' => '17:00',
    ]);
    $row = app(TimesheetReportService::class)->rows(collect([$log]))[0];

    expect($row['date'])->toBe('09-15-2026')
        ->and($row['time'])->toBe('08:00 AM – 12:00 PM / 01:00 PM – 05:00 PM')
        ->and($row['hours'])->toBe('8 hours');
});

test('a stepped-out day prints the gap pair as its own interval', function () {
    // The kiosk scenario: 8:00 in, early out 10:30, back 11:00, out again
    // 11:45, lunch, then the normal afternoon.
    $log = timesheetRowFor([
        'am_time_in' => '08:00',
        'am_time_out' => '10:30',
        'am_time_in_2' => '11:00',
        'am_time_out_2' => '11:45',
        'pm_time_in' => '13:00',
        'pm_time_out' => '17:00',
    ]);
    $row = app(TimesheetReportService::class)->rows(collect([$log]))[0];

    expect($row['time'])->toBe('08:00 AM – 10:30 AM / 11:00 AM – 11:45 AM / 01:00 PM – 05:00 PM')
        // 2h30m + 45m + 4h worked; the 10:30–11:00 step away is not counted.
        ->and($row['hours'])->toBe('7 hours 15 minutes');
});

test('an afternoon step-out prints after the main PM interval', function () {
    $log = timesheetRowFor([
        'am_time_in' => '08:00',
        'am_time_out' => '12:00',
        'pm_time_in' => '13:00',
        'pm_time_out' => '16:00',
        'pm_time_in_2' => '16:30',
        'pm_time_out_2' => '17:10',
    ]);
    $row = app(TimesheetReportService::class)->rows(collect([$log]))[0];

    expect($row['time'])->toBe('08:00 AM – 12:00 PM / 01:00 PM – 04:00 PM / 04:30 PM – 05:10 PM');
});

test('a dangling gap pair (no matching Out) is skipped, not printed half-open', function () {
    // The intern came back at 11:00 but the Out (2) was never recorded —
    // admin hasn't completed it yet. The row only shows provable intervals.
    $log = timesheetRowFor([
        'am_time_in' => '08:00',
        'am_time_out' => '10:30',
        'am_time_in_2' => '11:00',
        'pm_time_in' => '13:00',
        'pm_time_out' => '17:00',
    ]);
    $row = app(TimesheetReportService::class)->rows(collect([$log]))[0];

    expect($row['time'])->toBe('08:00 AM – 10:30 AM / 01:00 PM – 05:00 PM')
        ->and($row['hours'])->toBe('6 hours 30 minutes');
});

test('a day with no times prints an em dash', function () {
    $log = timesheetRowFor([]);
    $row = app(TimesheetReportService::class)->rows(collect([$log]))[0];

    expect($row['time'])->toBe('—');
});
