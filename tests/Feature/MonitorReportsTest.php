<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;

uses(RefreshDatabase::class);

/*
 * The monitor roles' reporting powers — scoped versions of what the admin
 * already had: CSV export of their interns' logbooks (with review status),
 * the Timesheet and Weekly Progress Report downloads, and the Daily Report
 * PDF. Every access is scoped by InternPolicy::monitor /
 * OjtLogPolicy::viewDailyReport — a monitor never sees another scope's
 * interns.
 *
 * Reuses the global helpers makeIntern / makeStaff / makeCoordinator /
 * makeSupervisor / makeOffice / makeActiveEnrollment / makeLog /
 * fullDutyTimes / attachReportPhoto defined in the sibling feature tests.
 */

beforeEach(function () {
    Storage::fake('public');
});

function monitorScenario(): array
{
    $office = makeOffice(['name' => 'Report Office']);
    $coordinator = makeCoordinator();
    $supervisor = makeSupervisor($office);
    $intern = makeIntern(['student_id' => 'T-9600', 'last_name' => 'Reportson', 'office_id' => $office->id, 'coordinator_id' => $coordinator->id]);
    $enrollment = makeActiveEnrollment($intern);
    $log = makeLog($intern, $enrollment, times: fullDutyTimes());

    $strangerOffice = makeOffice(['name' => 'Stranger Office']);
    $strangerSupervisor = makeSupervisor($strangerOffice);
    $stranger = makeIntern(['student_id' => 'T-9601', 'last_name' => 'Strangerson', 'office_id' => $strangerOffice->id]);
    $strangerEnrollment = makeActiveEnrollment($stranger);
    $strangerLog = makeLog($stranger, $strangerEnrollment, times: fullDutyTimes());

    return compact('office', 'coordinator', 'supervisor', 'intern', 'log', 'strangerSupervisor', 'stranger', 'strangerLog');
}

test('supervisor can export their office interns\' logbook as CSV with review status', function () {
    ['supervisor' => $supervisor, 'intern' => $intern, 'stranger' => $stranger] = monitorScenario();

    $response = $this->actingAs($supervisor)->get(route('monitor.export', [
        'from' => today()->startOfMonth()->toDateString(),
        'to' => today()->toDateString(),
    ]));

    $response->assertOk();

    $csv = $response->streamedContent();

    expect($csv)->toContain('Review Status')
        ->toContain($intern->full_name)
        ->not->toContain($stranger->full_name);
});

test('coordinator can export their assigned interns\' logbook as CSV', function () {
    ['coordinator' => $coordinator, 'intern' => $intern, 'stranger' => $stranger] = monitorScenario();

    $csv = $this->actingAs($coordinator)->get(route('monitor.export'))->streamedContent();

    expect($csv)->toContain($intern->full_name)
        ->not->toContain($stranger->full_name);
});

test('monitor can download their intern\'s timesheet — missing template redirects with a notice', function () {
    ['supervisor' => $supervisor, 'coordinator' => $coordinator, 'intern' => $intern, 'stranger' => $stranger] = monitorScenario();

    // No Timesheet template uploaded — the scoped monitor gets redirected
    // back to the intern page with a fix-it notice, not an error.
    $this->actingAs($supervisor)->get(route('monitor.interns.timesheet', $intern))
        ->assertRedirect(route('monitor.intern', $intern))
        ->assertSessionHas('status');

    $this->actingAs($coordinator)->get(route('monitor.interns.weekly-report', $intern))
        ->assertRedirect(route('monitor.intern', $intern))
        ->assertSessionHas('status');

    // A supervisor of a different office never gets as far as the document.
    $this->actingAs(makeSupervisor(makeOffice(['name' => 'Elsewhere'])))
        ->get(route('monitor.interns.timesheet', $intern))
        ->assertForbidden();
});

test('monitor can open their intern\'s daily report PDF but not another scope\'s', function () {
    ['supervisor' => $supervisor, 'coordinator' => $coordinator, 'intern' => $intern, 'log' => $log, 'stranger' => $stranger, 'strangerLog' => $strangerLog, 'strangerSupervisor' => $strangerSupervisor] = monitorScenario();
    attachReportPhoto($log, $intern);
    attachReportPhoto($strangerLog, $stranger);

    $this->actingAs($supervisor)->get(route('reports.show', $log))
        ->assertOk()
        ->assertHeader('Content-Type', 'application/pdf');

    $this->actingAs($coordinator)->get(route('reports.show', $log))->assertOk();

    // Another office's supervisor is denied; the log's own supervisor is not.
    $this->actingAs($supervisor)->get(route('reports.show', $strangerLog))->assertForbidden();
    $this->actingAs($strangerSupervisor)->get(route('reports.show', $strangerLog))->assertOk();
});

test('notifications inbox lists review activity and mark-all-read clears it', function () {
    $scenario = monitorScenario();
    $supervisor = $scenario['supervisor'];
    $intern = $scenario['intern'];

    $this->actingAs($supervisor)
        ->post(route('monitor.logs.review', $scenario['log']), ['action' => 'reject', 'comment' => 'Times do not match the logbook.'])
        ->assertSessionHasNoErrors();

    $this->post(route('logout'));

    // The intern's sidebar shows the unread count, and the inbox lists the decision.
    $page = $this->actingAs($intern)->get(route('notifications.index'));

    $page->assertOk()
        ->assertSee('was rejected by')
        ->assertSee('Times do not match the logbook.');

    expect($intern->fresh()->unreadNotifications()->count())->toBe(1);

    $this->post(route('notifications.read-all'))->assertRedirect(route('notifications.index'));

    expect($intern->fresh()->unreadNotifications()->count())->toBe(0);
});
