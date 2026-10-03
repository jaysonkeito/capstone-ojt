<?php

use App\Models\OjtLog;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

/*
 * The admin exports (logbook CSV, monthly summary PDF) and the live
 * "Today" board on the dashboard.
 */

test('logbook exports as CSV for a date range', function () {
    $intern = makeIntern();
    makeActiveEnrollment($intern);
    makeLog($intern, $intern->currentEnrollment, times: fullDutyTimes());

    $admin = makeStaff(['role' => 'admin']);

    $response = $this->actingAs($admin)->get(route('admin.logs.export', [
        'from' => today()->toDateString(),
        'to' => today()->toDateString(),
    ]));

    $response->assertOk();
    $response->assertHeader('Content-Type', 'text/csv; charset=UTF-8');

    $csv = $response->streamedContent();
    expect($csv)->toStartWith('Date,Intern')
        ->and($csv)->toContain('"Student ID"')
        ->and($csv)->toContain($intern->full_name)
        ->and($csv)->toContain($intern->student_id)
        ->and($csv)->toContain('8.00');
});

test('monthly summary renders a PDF with the month totals', function () {
    $intern = makeIntern();
    makeActiveEnrollment($intern);
    makeLog($intern, $intern->currentEnrollment, times: fullDutyTimes());

    $admin = makeStaff(['role' => 'admin']);

    $response = $this->actingAs($admin)->get(route('admin.interns.summary', [
        'intern' => $intern,
        'month' => now()->format('Y-m'),
    ]));

    $response->assertOk();
    $response->assertHeader('Content-Type', 'application/pdf');
    expect($response->getContent())->toStartWith('%PDF');

    $download = $this->actingAs($admin)->get(route('admin.interns.summary', [
        'intern' => $intern,
        'month' => now()->format('Y-m'),
        'download' => 1,
    ]));
    $download->assertDownload('MonthlySummary_'.$intern->student_id.'_'.now()->format('Y-m').'.pdf');
});

test('dashboard shows the live today board', function () {
    $intern = makeIntern();
    makeActiveEnrollment($intern);
    makeLog($intern, $intern->currentEnrollment, times: ['am_time_in' => '08:02']); // on duty, not clocked out

    $admin = makeStaff(['role' => 'admin']);

    $this->actingAs($admin)->get(route('admin.dashboard'))
        ->assertOk()
        ->assertSee('On duty now')
        ->assertSee($intern->full_name)
        ->assertSee('Top 3 highest hours')
        ->assertSee('Top 3 least hours');
});

test('the intern timesheet is unavailable until an admin uploads a template', function () {
    $intern = makeIntern(['first_name' => 'Jayson P', 'last_name' => 'Francisco']);
    makeActiveEnrollment($intern);
    makeLog($intern, $intern->currentEnrollment, times: fullDutyTimes());

    // No Timesheet template uploaded yet: the intern can't generate the form
    // and is redirected back to their dashboard with an explanatory notice.
    $this->actingAs($intern)->get(route('intern.timesheet'))
        ->assertRedirect(route('intern.dashboard'))
        ->assertSessionHas('status');
});

test('timesheet route is closed to admins and guests', function () {
    // Guest first — actingAs() below would otherwise leak into this check.
    $this->get(route('intern.timesheet'))->assertRedirect(route('login'));

    $admin = makeStaff(['role' => 'admin']);
    $this->actingAs($admin)->get(route('intern.timesheet'))->assertForbidden();
});

test('exports are closed to interns', function () {
    $intern = makeIntern();
    makeActiveEnrollment($intern);
    makeLog($intern, $intern->currentEnrollment, times: fullDutyTimes());

    $this->actingAs($intern)->get(route('admin.logs.export'))->assertForbidden();
    $this->actingAs($intern)->get(route('admin.interns.summary', ['intern' => $intern]))->assertForbidden();
});

/*
 * The My Time Frame page — the intern's duty days and hours readable on
 * screen, with the Export button that produces the Word download.
 */

test('my time frame shows the duty days and hour totals without downloading', function () {
    $intern = makeIntern();
    $enrollment = makeActiveEnrollment($intern);
    makeLog($intern, $enrollment, '2026-08-03', fullDutyTimes());
    makeLog($intern, $enrollment, '2026-08-04', fullDutyTimes());

    $response = $this->actingAs($intern)->get(route('intern.time-frame'));

    $response->assertOk()
        // The duty days print oldest first, same as the Word export, with
        // their clock times and hours.
        ->assertSee('08-03-2026')
        ->assertSee('08-04-2026')
        ->assertSee('08:00 AM – 12:00 PM')
        ->assertSee('8 hours')
        // The running total against the set's target.
        ->assertSee('16.00')
        ->assertSee('500.00')
        // The Export button hands off to the Word download.
        ->assertSee(route('intern.timesheet'));
});

test('my time frame is closed to admins and guests', function () {
    $this->get(route('intern.time-frame'))->assertRedirect(route('login'));

    $admin = makeStaff(['role' => 'admin']);
    $this->actingAs($admin)->get(route('intern.time-frame'))->assertForbidden();
});
