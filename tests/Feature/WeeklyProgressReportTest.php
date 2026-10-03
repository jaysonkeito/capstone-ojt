<?php

use App\Services\WeeklyProgressReportService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;

uses(RefreshDatabase::class);

/*
 * The intern's Weekly Progress Report: daily journal entries (message +
 * Documentation photo) compiled into the school's weekly form — one page per
 * Monday-start week, each laid out as the form's fixed Monday–Friday grid.
 * Only days with a completed journal are filled in; the rest stay blank.
 *
 * Reuses makeIntern(), makeActiveEnrollment(), makeLog() and fullDutyTimes()
 * (InternPhotoUploadTest) — global Pest helpers in this suite. 2026-08-03 is a
 * Monday, so 08-03..08-07 is one week (5 days) and 08-10..08-13 the next (4).
 */

it('groups duty days into labelled Monday-start weeks', function () {
    $intern = makeIntern();
    $enrollment = makeActiveEnrollment($intern);

    foreach (['2026-08-03', '2026-08-04', '2026-08-05', '2026-08-06', '2026-08-07'] as $date) {
        makeLog($intern, $enrollment, $date, ['notes' => "Worked on {$date}"]);
    }
    foreach (['2026-08-10', '2026-08-11', '2026-08-12', '2026-08-13'] as $date) {
        makeLog($intern, $enrollment, $date, ['notes' => "Worked on {$date}"]);
    }

    $weeks = app(WeeklyProgressReportService::class)
        ->groupIntoWeeks($enrollment->logs()->orderBy('date')->get());

    expect($weeks)->toHaveCount(2)
        ->and($weeks[0]['label'])->toBe('First week of August 2026')
        ->and($weeks[0]['logs'])->toHaveCount(5)
        ->and($weeks[1]['label'])->toBe('Second week of August 2026')
        ->and($weeks[1]['logs'])->toHaveCount(4);
});

it('folds a weekend duty day into its Monday-start week', function () {
    $intern = makeIntern();
    $enrollment = makeActiveEnrollment($intern);

    makeLog($intern, $enrollment, '2026-08-07', ['notes' => 'Friday']);
    makeLog($intern, $enrollment, '2026-08-08', ['notes' => 'Saturday, same week']);

    $weeks = app(WeeklyProgressReportService::class)
        ->groupIntoWeeks($enrollment->logs()->get());

    expect($weeks)->toHaveCount(1)
        ->and($weeks[0]['logs'])->toHaveCount(2);
});

it('lays each week onto fixed Monday-Friday columns, filling only completed journals', function () {
    $intern = makeIntern();
    $enrollment = makeActiveEnrollment($intern);

    // Tuesday 04: a completed journal — both a photo and a message.
    $tuesday = makeLog($intern, $enrollment, '2026-08-04', [
        'notes' => 'Encoded client records.',
        'photo_path' => 'ojt-photos/1/tuesday.jpg',
        ...fullDutyTimes(),
    ]);

    // Thursday 06: a message but NO photo — not a completed journal, so it
    // must stay blank on the form.
    makeLog($intern, $enrollment, '2026-08-06', [
        'notes' => 'Attendance only, forgot the photo.',
        ...fullDutyTimes(),
    ]);

    $slots = app(WeeklyProgressReportService::class)
        ->weekdaySlots(Carbon::parse('2026-08-05'), $enrollment->logs()->get());

    // Always five columns, always Monday through Friday with that week's dates,
    // no matter how few days were actually worked.
    expect($slots)->toHaveCount(5)
        ->and(array_column($slots, 'weekday'))->toBe(['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday'])
        ->and(array_column($slots, 'day'))->toBe(['03', '04', '05', '06', '07']);

    // Only Tuesday carries its log; every other weekday is blank — including
    // Thursday, which has a log but no photo.
    expect($slots[0]['log'])->toBeNull()
        ->and($slots[1]['log']?->id)->toBe($tuesday->id)
        ->and($slots[2]['log'])->toBeNull()
        ->and($slots[3]['log'])->toBeNull()
        ->and($slots[4]['log'])->toBeNull();

    // Filled slots carry the day's full date for the ${photoN_date} merge —
    // printed under the photo in the Documentation cells; blank slots too.
    expect(array_column($slots, 'photo_date'))->toBe([
        '', 'August 4, 2026', '', '', '',
    ]);
});

test('the weekly report is unavailable until an admin uploads a template', function () {
    $intern = makeIntern();
    $enrollment = makeActiveEnrollment($intern);
    makeLog($intern, $enrollment, '2026-08-03', ['notes' => 'First day']);

    // No Weekly Progress Report template has been uploaded, so the form can't
    // be generated — the intern is sent back to their dashboard with a notice
    // rather than handed an ad-hoc PDF.
    $this->actingAs($intern)->get(route('intern.weekly-report'))
        ->assertRedirect(route('intern.dashboard'))
        ->assertSessionHas('status');
});
