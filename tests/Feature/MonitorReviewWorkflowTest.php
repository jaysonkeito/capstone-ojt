<?php

use App\Models\OjtLog;
use App\Models\User;
use App\Notifications\LogFlagged;
use App\Notifications\LogReviewed;
use App\Notifications\ManualEntrySubmitted;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

/*
 * The log review workflow — the write actions that turned the two
 * monitor roles from spectators into participants:
 *
 *  - Supervisors approve or reject entries of interns placed at their
 *    office (a rejection always carries a reason the intern sees).
 *  - Coordinators flag an entry of an intern assigned to them for a
 *    second look — the office's supervisors get notified to adjudicate.
 *  - Supervisors record a missed scan manually; the entry starts as
 *    pending and the admins are notified to confirm it.
 *
 * Scoping is enforced by OjtLogPolicy / InternPolicy; hour totals keep
 * counting every entry regardless of review status.
 *
 * Reuses the global helpers makeIntern / makeStaff / makeCoordinator /
 * makeSupervisor / makeOffice / makeActiveEnrollment / makeLog /
 * fullDutyTimes defined in the sibling feature tests.
 */

function makeSecondOfficeLog(): OjtLog
{
    // A log belonging to an intern at a DIFFERENT office with a DIFFERENT
    // coordinator — the "stranger" entry no monitor may touch.
    $stranger = makeIntern(['student_id' => 'T-9500']);
    $enrollment = makeActiveEnrollment($stranger);

    return makeLog($stranger, $enrollment, times: fullDutyTimes());
}

test('supervisor can approve an entry at their office', function () {
    $office = makeOffice();
    $supervisor = makeSupervisor($office);
    $intern = makeIntern(['office_id' => $office->id]);
    $enrollment = makeActiveEnrollment($intern);
    $log = makeLog($intern, $enrollment, times: fullDutyTimes());

    $this->actingAs($supervisor)
        ->post(route('monitor.logs.review', $log), ['action' => 'approve'])
        ->assertRedirect(route('monitor.intern', $intern))
        ->assertSessionHasNoErrors();

    $log->refresh();

    expect($log->status)->toBe('approved')
        ->and($log->reviewed_by)->toBe($supervisor->id)
        ->and($log->reviewed_at)->not->toBeNull()
        // An approval notifies the intern.
        ->and($intern->notifications()->where('type', LogReviewed::class)->exists())->toBeTrue();
});

test('supervisor rejecting an entry requires a reason and notifies the intern', function () {
    $office = makeOffice();
    $supervisor = makeSupervisor($office);
    $intern = makeIntern(['office_id' => $office->id]);
    $enrollment = makeActiveEnrollment($intern);
    $log = makeLog($intern, $enrollment, times: fullDutyTimes());

    // No comment — refused.
    $this->actingAs($supervisor)
        ->post(route('monitor.logs.review', $log), ['action' => 'reject'])
        ->assertSessionHasErrors('comment');

    // With a reason — the entry flips to rejected and the intern is told why.
    $this->actingAs($supervisor)
        ->post(route('monitor.logs.review', $log), ['action' => 'reject', 'comment' => 'You left at 10am, this does not match the logbook.'])
        ->assertRedirect()
        ->assertSessionHasNoErrors();

    $log->refresh();

    expect($log->status)->toBe('rejected')
        ->and($log->review_comment)->toBe('You left at 10am, this does not match the logbook.')
        ->and($intern->fresh()->unreadNotifications()->count())->toBe(1);

    // Rejected time still counts toward hours — review is not a gate.
    expect((float) $log->hours_rendered)->toBe(8.0);
});

test('supervisor cannot review another office\'s entry', function () {
    $supervisor = makeSupervisor(makeOffice());
    $strangerLog = makeSecondOfficeLog();

    $this->actingAs($supervisor)
        ->post(route('monitor.logs.review', $strangerLog), ['action' => 'approve'])
        ->assertForbidden();

    $this->actingAs($supervisor)
        ->post(route('monitor.logs.review', $strangerLog), ['action' => 'reject', 'comment' => 'nope'])
        ->assertForbidden();

    expect($strangerLog->refresh()->status)->toBe('approved');
});

test('coordinator can flag their intern\'s entry and the office supervisors are notified', function () {
    $office = makeOffice();
    $coordinator = makeCoordinator();
    $supervisor = makeSupervisor($office);
    $intern = makeIntern(['office_id' => $office->id, 'coordinator_id' => $coordinator->id]);
    $enrollment = makeActiveEnrollment($intern);
    $log = makeLog($intern, $enrollment, times: fullDutyTimes());

    $this->actingAs($coordinator)
        ->post(route('monitor.logs.review', $log), ['action' => 'flag', 'comment' => 'Please double-check this day.'])
        ->assertRedirect(route('monitor.intern', $intern))
        ->assertSessionHasNoErrors();

    $log->refresh();

    expect($log->status)->toBe('pending')
        ->and($log->review_comment)->toBe('Please double-check this day.')
        ->and($supervisor->fresh()->notifications()->where('type', LogFlagged::class)->exists())->toBeTrue();
});

test('a flag with no office supervisor falls back to the admins', function () {
    $coordinator = makeCoordinator();
    $intern = makeIntern(['coordinator_id' => $coordinator->id]); // no office, no supervisors
    $enrollment = makeActiveEnrollment($intern);
    $log = makeLog($intern, $enrollment, times: fullDutyTimes());
    $admin = makeStaff(['role' => 'admin']);

    $this->actingAs($coordinator)
        ->post(route('monitor.logs.review', $log), ['action' => 'flag', 'comment' => 'Check this.'])
        ->assertSessionHasNoErrors();

    expect($admin->fresh()->notifications()->where('type', LogFlagged::class)->exists())->toBeTrue();
});

test('coordinator cannot flag another coordinator\'s intern and cannot approve or reject', function () {
    $coordinator = makeCoordinator();
    $strangerLog = makeSecondOfficeLog();

    $this->actingAs($coordinator)
        ->post(route('monitor.logs.review', $strangerLog), ['action' => 'flag', 'comment' => 'not mine'])
        ->assertForbidden();

    // Approve/reject is the supervisors' call — the flag policy does not cover it.
    $intern = makeIntern(['coordinator_id' => $coordinator->id]);
    $enrollment = makeActiveEnrollment($intern);
    $myLog = makeLog($intern, $enrollment, times: fullDutyTimes());

    $this->actingAs($coordinator)
        ->post(route('monitor.logs.review', $myLog), ['action' => 'approve'])
        ->assertForbidden();

    expect($myLog->refresh()->status)->toBe('approved');
});

test('supervisor records a missed scan as a pending entry and the admins are notified', function () {
    $office = makeOffice();
    $supervisor = makeSupervisor($office);
    $intern = makeIntern(['office_id' => $office->id]);
    makeActiveEnrollment($intern);
    $admin = makeStaff(['role' => 'admin']);

    $this->actingAs($supervisor)
        ->post(route('monitor.logs.store'), [
            'user_id' => $intern->id,
            'date' => today()->subDay()->toDateString(),
            'am_time_in' => '08:00',
            'am_time_out' => '12:00',
            'notes' => 'Intern forgot to scan in the morning.',
        ])
        ->assertRedirect(route('monitor.intern', $intern))
        ->assertSessionHasNoErrors();

    $log = OjtLog::where('user_id', $intern->id)->firstOrFail();

    expect($log->status)->toBe('pending')
        ->and($log->logged_by)->toBe($supervisor->id)
        ->and(substr((string) $log->am_time_in, 0, 5))->toBe('08:00')
        ->and((float) $log->regular_hours)->toBe(4.0)
        ->and($admin->fresh()->notifications()->where('type', ManualEntrySubmitted::class)->exists())->toBeTrue();
});

test('supervisor cannot record an entry for an intern at another office', function () {
    $supervisor = makeSupervisor(makeOffice());
    $stranger = makeIntern(['student_id' => 'T-9501']);
    makeActiveEnrollment($stranger);

    $this->actingAs($supervisor)
        ->post(route('monitor.logs.store'), [
            'user_id' => $stranger->id,
            'date' => today()->subDay()->toDateString(),
            'am_time_in' => '08:00',
        ])
        ->assertForbidden();

    expect(OjtLog::count())->toBe(0);
});

test('supervisor manual entry is refused when blank, duplicated, or without an active OJT set', function () {
    $office = makeOffice();
    $supervisor = makeSupervisor($office);
    $intern = makeIntern(['office_id' => $office->id]);
    makeActiveEnrollment($intern);

    // Blank — nothing to record.
    $this->actingAs($supervisor)
        ->post(route('monitor.logs.store'), ['user_id' => $intern->id, 'date' => today()->subDay()->toDateString()])
        ->assertSessionHasErrors('am_time_in');

    // Duplicate date — the intern already has an entry that day.
    makeLog($intern, $intern->currentEnrollment, times: fullDutyTimes());
    $this->actingAs($supervisor)
        ->post(route('monitor.logs.store'), [
            'user_id' => $intern->id,
            'date' => today()->toDateString(),
            'am_time_in' => '08:00',
        ])
        ->assertSessionHasErrors('date');

    // No active OJT set.
    $unplaced = makeIntern(['student_id' => 'T-9502', 'office_id' => $office->id]);
    $this->actingAs($supervisor)
        ->post(route('monitor.logs.store'), [
            'user_id' => $unplaced->id,
            'date' => today()->subDays(2)->toDateString(),
            'am_time_in' => '08:00',
        ])
        ->assertSessionHasErrors('user_id');

    expect(OjtLog::count())->toBe(1);
});

test('the missing-entry form page is supervisor-only', function () {
    $supervisor = makeSupervisor(makeOffice());
    $coordinator = makeCoordinator();
    $intern = makeIntern();

    $this->actingAs($supervisor)->get(route('monitor.logs.create'))
        ->assertOk()
        ->assertSee('Add Missing Entry');

    $this->actingAs($coordinator)->get(route('monitor.logs.create'))->assertForbidden();
    $this->actingAs($intern)->get(route('monitor.logs.create'))->assertForbidden();
});
