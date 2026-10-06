<?php

use App\Models\LogRequest;
use App\Models\OjtLog;
use App\Notifications\AttendanceRequestDecided;
use App\Notifications\AttendanceRequestSubmitted;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

/*
 * The intern's attendance requests — corrections to a recorded entry's
 * times (applied on approval, hours recomputed) and absence reports for
 * days with no entry (informational only, never a log). The office
 * supervisor and the coordinator decide within their scope; the intern
 * hears the outcome either way.
 *
 * Reuses the global helpers makeIntern / makeStaff / makeCoordinator /
 * makeSupervisor / makeOffice / makeActiveEnrollment / makeLog /
 * fullDutyTimes defined in the sibling feature tests.
 */

function requestWorld(): array
{
    $office = makeOffice();
    $coordinator = makeCoordinator();
    $supervisor = makeSupervisor($office);
    $intern = makeIntern(['student_id' => 'T-9800', 'office_id' => $office->id, 'coordinator_id' => $coordinator->id]);
    $enrollment = makeActiveEnrollment($intern);
    $admin = makeStaff(['role' => 'admin']);

    return compact('office', 'coordinator', 'supervisor', 'intern', 'enrollment', 'admin');
}

test('intern files a correction and the supervisors plus coordinator are notified', function () {
    ['supervisor' => $supervisor, 'coordinator' => $coordinator, 'intern' => $intern, 'enrollment' => $enrollment] = requestWorld();
    $log = makeLog($intern, $enrollment, times: fullDutyTimes());

    $this->actingAs($intern)
        ->post(route('intern.requests.store'), [
            'type' => 'correction',
            'date' => today()->toDateString(),
            'am_time_in' => '08:15',
            'reason' => 'The kiosk read my QR late.',
        ])
        ->assertRedirect(route('intern.requests.index'))
        ->assertSessionHasNoErrors();

    $request = LogRequest::firstOrFail();

    expect($request->type)->toBe('correction')
        ->and($request->ojt_log_id)->toBe($log->id)
        ->and(substr((string) $request->am_time_in, 0, 5))->toBe('08:15')
        ->and($supervisor->fresh()->notifications()->where('type', AttendanceRequestSubmitted::class)->exists())->toBeTrue()
        ->and($coordinator->fresh()->notifications()->where('type', AttendanceRequestSubmitted::class)->exists())->toBeTrue();
});

test('a correction for a day without an entry is refused — report an absence instead', function () {
    ['intern' => $intern] = requestWorld();

    $this->actingAs($intern)
        ->post(route('intern.requests.store'), [
            'type' => 'correction',
            'date' => today()->subDay()->toDateString(),
            'am_time_in' => '08:00',
            'reason' => 'no entry that day',
        ])
        ->assertSessionHasErrors('date');

    expect(LogRequest::count())->toBe(0);
});

test('an absence report is refused when the day already has an entry', function () {
    ['intern' => $intern, 'enrollment' => $enrollment] = requestWorld();
    makeLog($intern, $enrollment, times: fullDutyTimes());

    $this->actingAs($intern)
        ->post(route('intern.requests.store'), [
            'type' => 'absence',
            'date' => today()->toDateString(),
            'reason' => 'sick',
        ])
        ->assertSessionHasErrors('date');

    expect(LogRequest::count())->toBe(0);
});

test('an absence report for an unlogged day is accepted and never creates a log', function () {
    ['intern' => $intern] = requestWorld();

    $this->actingAs($intern)
        ->post(route('intern.requests.store'), [
            'type' => 'absence',
            'date' => today()->subDay()->toDateString(),
            'reason' => 'Medical appointment.',
        ])
        ->assertSessionHasNoErrors();

    expect(LogRequest::firstOrFail()->type)->toBe('absence')
        ->and(OjtLog::count())->toBe(0);
});

test('only one pending request per date', function () {
    ['intern' => $intern] = requestWorld();

    $this->actingAs($intern)->post(route('intern.requests.store'), [
        'type' => 'absence', 'date' => today()->subDay()->toDateString(), 'reason' => 'first',
    ])->assertSessionHasNoErrors();

    $this->actingAs($intern)->post(route('intern.requests.store'), [
        'type' => 'absence', 'date' => today()->subDay()->toDateString(), 'reason' => 'second',
    ])->assertSessionHasErrors('date');

    expect(LogRequest::count())->toBe(1);
});

test('supervisor approving a correction updates the entry and recomputes hours', function () {
    ['supervisor' => $supervisor, 'intern' => $intern, 'enrollment' => $enrollment] = requestWorld();

    // The scan recorded a late arrival; the intern claims 8:00.
    $log = makeLog($intern, $enrollment, times: [
        'am_time_in' => '09:00', 'am_time_out' => '12:00',
        'pm_time_in' => '13:00', 'pm_time_out' => '17:00',
    ]);

    $request = LogRequest::create([
        'intern_id' => $intern->id, 'ojt_log_id' => $log->id,
        'type' => 'correction', 'date' => today()->toDateString(),
        'am_time_in' => '08:00',
        'reason' => 'The kiosk read my QR late.',
    ]);

    $this->actingAs($supervisor)
        ->post(route('monitor.requests.log.decide', $request), ['action' => 'approved'])
        ->assertRedirect(route('monitor.requests.index'))
        ->assertSessionHasNoErrors();

    $log->refresh();

    expect(substr((string) $log->am_time_in, 0, 5))->toBe('08:00')
        ->and((float) $log->regular_hours)->toBe(8.0)
        ->and($request->fresh()->status)->toBe('approved')
        // The intern hears the outcome.
        ->and($intern->notifications()->where('type', AttendanceRequestDecided::class)->exists())->toBeTrue();
});

test('rejecting a request requires a reason the intern will see', function () {
    ['supervisor' => $supervisor, 'intern' => $intern, 'enrollment' => $enrollment] = requestWorld();
    $log = makeLog($intern, $enrollment, times: fullDutyTimes());

    $request = LogRequest::create([
        'intern_id' => $intern->id, 'ojt_log_id' => $log->id,
        'type' => 'correction', 'date' => today()->toDateString(),
        'am_time_in' => '07:30', 'reason' => 'Times are wrong.',
    ]);

    $this->actingAs($supervisor)
        ->post(route('monitor.requests.log.decide', $request), ['action' => 'rejected'])
        ->assertSessionHasErrors('comment');

    $this->actingAs($supervisor)
        ->post(route('monitor.requests.log.decide', $request), ['action' => 'rejected', 'comment' => 'No witness for that time.'])
        ->assertSessionHasNoErrors();

    // The entry is untouched by a rejection.
    expect(substr((string) $log->refresh()->am_time_in, 0, 5))->toBe('08:00')
        ->and($request->fresh()->status)->toBe('rejected')
        ->and($request->fresh()->decision_comment)->toBe('No witness for that time.');
});

test('the coordinator can decide their intern\'s request but not another scope\'s', function () {
    ['coordinator' => $coordinator, 'intern' => $intern] = requestWorld();

    $strangerIntern = makeIntern(['student_id' => 'T-9801']);
    $strangerRequest = LogRequest::create([
        'intern_id' => $strangerIntern->id,
        'type' => 'absence', 'date' => today()->subDay()->toDateString(), 'reason' => 'not mine',
    ]);

    $this->actingAs($coordinator)
        ->post(route('monitor.requests.log.decide', $strangerRequest), ['action' => 'approved'])
        ->assertForbidden();

    $myRequest = LogRequest::create([
        'intern_id' => $intern->id,
        'type' => 'absence', 'date' => today()->subDay()->toDateString(), 'reason' => 'mine',
    ]);

    $this->actingAs($coordinator)
        ->post(route('monitor.requests.log.decide', $myRequest), ['action' => 'approved'])
        ->assertSessionHasNoErrors();

    expect($myRequest->fresh()->status)->toBe('approved');
});

test('the admin can decide any request as the fallback adjudicator', function () {
    ['admin' => $admin, 'intern' => $intern, 'enrollment' => $enrollment] = requestWorld();
    $log = makeLog($intern, $enrollment, times: fullDutyTimes());

    $request = LogRequest::create([
        'intern_id' => $intern->id, 'ojt_log_id' => $log->id,
        'type' => 'correction', 'date' => today()->toDateString(),
        'pm_time_out' => '17:30', 'reason' => 'Stayed late.',
    ]);

    $this->actingAs($admin)
        ->post(route('monitor.requests.log.decide', $request), ['action' => 'approved'])
        ->assertSessionHasNoErrors();

    expect(substr((string) $log->refresh()->pm_time_out, 0, 5))->toBe('17:30');
});

test('approving a correction that touches only PM slots leaves AM times alone', function () {
    ['supervisor' => $supervisor, 'intern' => $intern, 'enrollment' => $enrollment] = requestWorld();
    $log = makeLog($intern, $enrollment, times: fullDutyTimes());

    $request = LogRequest::create([
        'intern_id' => $intern->id, 'ojt_log_id' => $log->id,
        'type' => 'correction', 'date' => today()->toDateString(),
        'pm_time_in' => '13:30', 'pm_time_out' => '17:00',
        'reason' => 'Came back late from lunch.',
    ]);

    $this->actingAs($supervisor)
        ->post(route('monitor.requests.log.decide', $request), ['action' => 'approved'])
        ->assertSessionHasNoErrors();

    $log->refresh();

    expect(substr((string) $log->am_time_in, 0, 5))->toBe('08:00')
        ->and(substr((string) $log->pm_time_in, 0, 5))->toBe('13:30');
});

test('an intern cannot file a request for a future date', function () {
    ['intern' => $intern] = requestWorld();

    $this->actingAs($intern)
        ->post(route('intern.requests.store'), [
            'type' => 'absence', 'date' => today()->addDay()->toDateString(), 'reason' => 'planned',
        ])
        ->assertSessionHasErrors('date');
});

/*
 * Withdrawing and deleting attendance requests — an intern can withdraw
 * their own filing while it still awaits a decision; once decided, the
 * outcome is part of the record. The System Admin can delete pending
 * requests entirely (junk/duplicate cleanup); decided ones stay.
 */

function filePendingAbsence(array $who, string $reason = 'Sick.'): LogRequest
{
    test()->actingAs($who['intern'])
        ->post(route('intern.requests.store'), [
            'type' => 'absence',
            'date' => today()->toDateString(),
            'reason' => $reason,
        ]);

    return LogRequest::firstOrFail();
}

test('an intern can withdraw their own pending request', function () {
    $world = requestWorld();
    $request = filePendingAbsence($world);

    expect(LogRequest::count())->toBe(1);

    $this->actingAs($world['intern'])
        ->delete(route('intern.requests.destroy', $request))
        ->assertRedirect(route('intern.requests.index'));

    expect(LogRequest::count())->toBe(0);
});

test('a decided request can no longer be withdrawn', function () {
    $world = requestWorld();
    $request = filePendingAbsence($world);

    $this->actingAs($world['supervisor'])
        ->post(route('monitor.requests.log.decide', $request), ['action' => 'approved']);

    $this->actingAs($world['intern'])
        ->delete(route('intern.requests.destroy', $request))
        ->assertForbidden();

    expect(LogRequest::count())->toBe(1);
});

test('an intern cannot withdraw another intern\'s request', function () {
    $world = requestWorld();
    $request = filePendingAbsence($world);

    $stranger = makeIntern(['student_id' => 'T-9877']);
    makeActiveEnrollment($stranger);

    $this->actingAs($stranger)
        ->delete(route('intern.requests.destroy', $request))
        ->assertForbidden();

    expect(LogRequest::count())->toBe(1);
});

test('the System Admin can delete a pending attendance request', function () {
    $world = requestWorld();
    $request = filePendingAbsence($world, 'junk filing.');

    $this->actingAs($world['admin'])
        ->delete(route('admin.requests.attendance.destroy', $request))
        ->assertRedirect(route('admin.requests.index'));

    expect(LogRequest::count())->toBe(0);
});

test('the System Admin cannot delete a decided attendance request', function () {
    $world = requestWorld();
    $request = filePendingAbsence($world);

    $this->actingAs($world['supervisor'])
        ->post(route('monitor.requests.log.decide', $request), ['action' => 'approved']);

    $this->actingAs($world['admin'])
        ->delete(route('admin.requests.attendance.destroy', $request))
        ->assertStatus(422);

    expect(LogRequest::count())->toBe(1);
});
