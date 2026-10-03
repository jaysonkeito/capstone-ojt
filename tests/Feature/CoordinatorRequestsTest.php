<?php

use App\Models\CompletionRecommendation;
use App\Models\PlacementRequest;
use App\Models\User;
use App\Notifications\RequestDecided;
use App\Notifications\RequestSubmitted;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

/*
 * The coordinator's requests to the System Admin — placement proposals
 * (approval assigns the office) and completion recommendations (approval
 * closes the intern's OJT set through OjtEnrollmentService, the single
 * place sets are closed). Rejections always carry a reason the
 * coordinator sees; only one pending request of each kind per intern.
 *
 * Reuses the global helpers makeIntern / makeStaff / makeCoordinator /
 * makeSupervisor / makeOffice / makeActiveEnrollment / makeLog defined in
 * the sibling feature tests.
 */

function requestScenario(): array
{
    $office = makeOffice(['name' => 'Target Office']);
    $otherOffice = makeOffice(['name' => 'Other Office']);
    $coordinator = makeCoordinator();
    $strangerCoordinator = makeCoordinator();
    $admin = makeStaff(['role' => 'admin']);
    $intern = makeIntern(['student_id' => 'T-9700', 'coordinator_id' => $coordinator->id]);
    $otherIntern = makeIntern(['student_id' => 'T-9701', 'coordinator_id' => $strangerCoordinator->id]);

    return compact('office', 'otherOffice', 'coordinator', 'strangerCoordinator', 'admin', 'intern', 'otherIntern');
}

test('coordinator submits a placement request and the admins are notified', function () {
    ['office' => $office, 'coordinator' => $coordinator, 'admin' => $admin, 'intern' => $intern] = requestScenario();

    $this->actingAs($coordinator)
        ->post(route('monitor.requests.placement.store'), [
            'intern_id' => $intern->id,
            'office_id' => $office->id,
            'note' => 'Intern requested this office.',
        ])
        ->assertRedirect(route('monitor.requests.index'))
        ->assertSessionHasNoErrors();

    $request = PlacementRequest::firstOrFail();

    expect($request->status)->toBe('pending')
        ->and($request->coordinator_id)->toBe($coordinator->id)
        ->and($request->office_id)->toBe($office->id)
        ->and($admin->fresh()->notifications()->where('type', RequestSubmitted::class)->exists())->toBeTrue();
});

test('only one pending placement request per intern', function () {
    ['office' => $office, 'otherOffice' => $otherOffice, 'coordinator' => $coordinator, 'intern' => $intern] = requestScenario();

    $this->actingAs($coordinator)->post(route('monitor.requests.placement.store'), [
        'intern_id' => $intern->id, 'office_id' => $office->id,
    ])->assertSessionHasNoErrors();

    $this->actingAs($coordinator)->post(route('monitor.requests.placement.store'), [
        'intern_id' => $intern->id, 'office_id' => $otherOffice->id,
    ])->assertSessionHasErrors('intern_id');

    expect(PlacementRequest::count())->toBe(1);
});

test('admin approves a placement and the intern is assigned to the office', function () {
    ['office' => $office, 'coordinator' => $coordinator, 'admin' => $admin, 'intern' => $intern] = requestScenario();
    $request = PlacementRequest::create([
        'intern_id' => $intern->id, 'coordinator_id' => $coordinator->id,
        'office_id' => $office->id, 'status' => 'pending',
    ]);

    $this->actingAs($admin)
        ->post(route('admin.requests.placement.decide', $request), ['action' => 'approved'])
        ->assertRedirect(route('admin.requests.index'))
        ->assertSessionHasNoErrors();

    expect($intern->fresh()->office_id)->toBe($office->id)
        ->and($request->fresh()->status)->toBe('approved')
        ->and($request->fresh()->decided_by)->toBe($admin->id)
        ->and($coordinator->fresh()->notifications()->where('type', RequestDecided::class)->exists())->toBeTrue();
});

test('admin rejecting a placement requires a reason and leaves the intern unplaced', function () {
    ['office' => $office, 'coordinator' => $coordinator, 'admin' => $admin, 'intern' => $intern] = requestScenario();
    $request = PlacementRequest::create([
        'intern_id' => $intern->id, 'coordinator_id' => $coordinator->id,
        'office_id' => $office->id, 'status' => 'pending',
    ]);

    $this->actingAs($admin)
        ->post(route('admin.requests.placement.decide', $request), ['action' => 'rejected'])
        ->assertSessionHasErrors('comment');

    $this->actingAs($admin)
        ->post(route('admin.requests.placement.decide', $request), ['action' => 'rejected', 'comment' => 'Office is full this term.'])
        ->assertSessionHasNoErrors();

    expect($intern->fresh()->office_id)->toBeNull()
        ->and($request->fresh()->status)->toBe('rejected')
        ->and($request->fresh()->decision_comment)->toBe('Office is full this term.');
});

test('coordinator cannot request a placement for another coordinator\'s intern', function () {
    ['otherOffice' => $otherOffice, 'coordinator' => $coordinator, 'otherIntern' => $otherIntern] = requestScenario();

    $this->actingAs($coordinator)
        ->post(route('monitor.requests.placement.store'), [
            'intern_id' => $otherIntern->id, 'office_id' => $otherOffice->id,
        ])
        ->assertForbidden();

    expect(PlacementRequest::count())->toBe(0);
});

test('supervisor cannot raise placement or completion requests', function () {
    ['office' => $office, 'coordinator' => $coordinator, 'intern' => $intern] = requestScenario();
    $supervisor = makeSupervisor($office);

    $this->actingAs($supervisor)
        ->post(route('monitor.requests.placement.store'), ['intern_id' => $intern->id, 'office_id' => $office->id])
        ->assertForbidden();

    $this->actingAs($supervisor)
        ->post(route('monitor.requests.completion.store'), ['intern_id' => $intern->id])
        ->assertForbidden();

    expect(PlacementRequest::count())->toBe(0)
        ->and(CompletionRecommendation::count())->toBe(0);
});

test('completion recommendation requires the target hours to be reached', function () {
    ['coordinator' => $coordinator, 'intern' => $intern] = requestScenario();
    makeActiveEnrollment($intern); // 0h of 500h

    $this->actingAs($coordinator)
        ->post(route('monitor.requests.completion.store'), ['intern_id' => $intern->id])
        ->assertSessionHasErrors('intern_id');

    expect(CompletionRecommendation::count())->toBe(0);
});

test('admin approving a completion closes the OJT set through the enrollment service', function () {
    ['coordinator' => $coordinator, 'admin' => $admin, 'intern' => $intern] = requestScenario();

    // A small set the intern has already filled — the set (and the user's
    // mirror target_hours) come from the enrollment service, the same path
    // the admin's "Start New Set" uses.
    $enrollment = app(App\Support\OjtEnrollmentService::class)->startNewSet($intern, 'Internship OJT', 8);
    makeLog($intern, $enrollment, times: fullDutyTimes());

    $recommendation = CompletionRecommendation::create([
        'intern_id' => $intern->id, 'coordinator_id' => $coordinator->id, 'status' => 'pending',
    ]);

    $this->actingAs($admin)
        ->post(route('admin.requests.completion.decide', $recommendation), ['action' => 'approved'])
        ->assertSessionHasNoErrors();

    expect($enrollment->fresh()->status)->toBe('completed')
        ->and($enrollment->fresh()->completed_at)->not->toBeNull()
        ->and($intern->fresh()->ojt_status)->toBe('completed')
        ->and($coordinator->fresh()->notifications()->where('type', RequestDecided::class)->exists())->toBeTrue();
});

test('only one pending completion recommendation per intern', function () {
    ['coordinator' => $coordinator, 'intern' => $intern] = requestScenario();

    $enrollment = app(App\Support\OjtEnrollmentService::class)->startNewSet($intern, 'Internship OJT', 8);
    makeLog($intern, $enrollment, times: fullDutyTimes());

    $this->actingAs($coordinator)->post(route('monitor.requests.completion.store'), ['intern_id' => $intern->id])->assertSessionHasNoErrors();
    $this->actingAs($coordinator)->post(route('monitor.requests.completion.store'), ['intern_id' => $intern->id])->assertSessionHasErrors('intern_id');

    expect(CompletionRecommendation::count())->toBe(1);
});

test('both request queues render for admins and monitors', function () {
    ['coordinator' => $coordinator, 'admin' => $admin, 'intern' => $intern] = requestScenario();
    makeActiveEnrollment($intern);

    $this->actingAs($admin)->get(route('admin.requests.index'))->assertOk()->assertSee('Placement requests');
    $this->actingAs($coordinator)->get(route('monitor.requests.index'))->assertOk()->assertSee('Attendance requests');
});
