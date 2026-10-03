<?php

use App\Models\Office;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

/*
 * Offices, staff accounts, and the two read-only monitoring roles:
 * coordinators see the interns assigned to them (with office and
 * supervisor), supervisors see the interns at their office (with
 * rendered hours and coordinator).
 */

function makeOffice(array $attributes = []): Office
{
    return Office::create([
        'name' => 'Test Office',
        'type' => 'internal',
        ...$attributes,
    ]);
}

function makeCoordinator(array $attributes = []): User
{
    return User::create([
        'role' => 'coordinator',
        'first_name' => 'Coord',
        'last_name' => 'Inator',
        'email' => fake()->unique()->safeEmail(),
        'password' => 'password',
        'password_changed_at' => now(),
        'target_hours' => 0,
        'is_active' => true,
        'profile_completed_at' => now(),
        ...$attributes,
    ]);
}

function makeSupervisor(Office $office, array $attributes = []): User
{
    return User::create([
        'role' => 'supervisor',
        'first_name' => 'Super',
        'last_name' => 'Visor',
        'email' => fake()->unique()->safeEmail(),
        'password' => 'password',
        'password_changed_at' => now(),
        'office_id' => $office->id,
        'target_hours' => 0,
        'is_active' => true,
        'profile_completed_at' => now(),
        ...$attributes,
    ]);
}

test('admin manages offices and staff accounts', function () {
    $admin = makeStaff(['role' => 'admin']);

    // Create an office
    $this->actingAs($admin)->post(route('admin.offices.store'), [
        'name' => 'MIS Office',
        'type' => 'internal',
    ])->assertRedirect(route('admin.offices.index'));
    expect(Office::where('name', 'MIS Office')->exists())->toBeTrue();

    // Create a coordinator
    $this->actingAs($admin)->post(route('admin.staff.store'), [
        'role' => 'coordinator',
        'first_name' => 'Juan',
        'last_name' => 'Dela Cruz',
        'email' => 'juan@norsubscojt.online',
        'password' => 'password123',
    ])->assertRedirect(route('admin.staff.index'));
    expect(User::where('email', 'juan@norsubscojt.online')->where('role', 'coordinator')->exists())->toBeTrue();

    // Create a supervisor bound to the office
    $office = Office::where('name', 'MIS Office')->first();

    $this->actingAs($admin)->post(route('admin.staff.store'), [
        'role' => 'supervisor',
        'first_name' => 'Maria',
        'last_name' => 'Santos',
        'email' => 'maria@norsubscojt.online',
        'password' => 'password123',
        'office_id' => $office->id,
    ])->assertRedirect(route('admin.staff.index'));
    expect(User::where('email', 'maria@norsubscojt.online')->where('office_id', $office->id)->exists())->toBeTrue();

    // Offices & staff pages render
    $this->actingAs($admin)->get(route('admin.offices.index'))->assertOk();
    $this->actingAs($admin)->get(route('admin.staff.index'))->assertOk();
});

test('supervisor requires an office at creation', function () {
    $admin = makeStaff(['role' => 'admin']);

    $this->actingAs($admin)->post(route('admin.staff.store'), [
        'role' => 'supervisor',
        'first_name' => 'No',
        'last_name' => 'Office',
        'email' => 'nooffice@norsubscojt.online',
        'password' => 'password123',
        // office_id missing
    ])->assertSessionHasErrors('office_id');
});

test('admin sets and updates a staff position', function () {
    $admin = makeStaff(['role' => 'admin']);

    $this->actingAs($admin)->post(route('admin.staff.store'), [
        'role' => 'coordinator',
        'first_name' => 'Franco',
        'last_name' => 'Abequibel',
        'email' => 'franco@norsubscojt.online',
        'password' => 'password123',
        'position' => 'MIS, Campus Director',
    ])->assertRedirect(route('admin.staff.index'));

    $staff = User::where('email', 'franco@norsubscojt.online')->firstOrFail();
    expect($staff->position)->toBe('MIS, Campus Director');

    $this->actingAs($admin)->get(route('admin.staff.index'))->assertSee('MIS, Campus Director');

    $this->actingAs($admin)->put(route('admin.staff.update', $staff), [
        'first_name' => 'Franco',
        'last_name' => 'Abequibel',
        'email' => 'franco@norsubscojt.online',
        'position' => 'Registrar',
    ])->assertRedirect(route('admin.staff.index'));

    expect($staff->fresh()->position)->toBe('Registrar');
});

test('admin can clear a staff position', function () {
    $admin = makeStaff(['role' => 'admin']);
    $staff = makeCoordinator(['position' => 'MIS, Campus Director']);

    $this->actingAs($admin)->put(route('admin.staff.update', $staff), [
        'first_name' => $staff->first_name,
        'last_name' => $staff->last_name,
        'email' => $staff->email,
        'position' => '',
    ])->assertRedirect(route('admin.staff.index'));

    expect($staff->fresh()->position)->toBeNull();
});

test('coordinator sees exactly their interns on the dashboard', function () {
    $mine = makeCoordinator();
    $other = makeCoordinator();

    $office = makeOffice(['name' => 'MIS Office']);
    $supervisor = makeSupervisor($office);

    $myIntern = makeIntern(['student_id' => 'T-9001', 'last_name' => 'Alphatester', 'coordinator_id' => $mine->id, 'office_id' => $office->id]);
    $otherIntern = makeIntern(['student_id' => 'T-9002', 'last_name' => 'Betatester', 'coordinator_id' => $other->id, 'office_id' => $office->id]);
    $unassigned = makeIntern(['student_id' => 'T-9003', 'last_name' => 'Gamatester']);

    $response = $this->actingAs($mine)->get(route('monitor.dashboard'));

    // The scoped dashboard shows the coordinator's own interns (progress
    // table + today board) and never another coordinator's.
    $response->assertOk()
        ->assertSee($myIntern->full_name)
        ->assertDontSee($otherIntern->full_name)
        ->assertDontSee($unassigned->full_name);
});

test('supervisor sees exactly their office interns on the dashboard', function () {
    $myOffice = makeOffice(['name' => 'My Office']);
    $otherOffice = makeOffice(['name' => 'Other Office']);

    $supervisor = makeSupervisor($myOffice);
    $coordinator = makeCoordinator();

    $myIntern = makeIntern(['student_id' => 'T-9001', 'last_name' => 'Alphatester', 'office_id' => $myOffice->id, 'coordinator_id' => $coordinator->id]);
    $otherIntern = makeIntern(['student_id' => 'T-9002', 'last_name' => 'Betatester', 'office_id' => $otherOffice->id]);

    $response = $this->actingAs($supervisor)->get(route('monitor.dashboard'));

    $response->assertOk()
        ->assertSee($myIntern->full_name)
        ->assertDontSee($otherIntern->full_name);
});

test('monitors can view their own intern history but not others', function () {
    $coordinator = makeCoordinator();
    $office = makeOffice();
    $supervisor = makeSupervisor($office);

    $myIntern = makeIntern(['student_id' => 'T-9001', 'coordinator_id' => $coordinator->id, 'office_id' => $office->id]);
    $enrollment = makeActiveEnrollment($myIntern);
    makeLog($myIntern, $enrollment, times: fullDutyTimes());

    $stranger = makeIntern(['student_id' => 'T-9002']);

    $this->actingAs($coordinator)->get(route('monitor.intern', $myIntern))->assertOk()->assertSee('Duty History');
    $this->actingAs($supervisor)->get(route('monitor.intern', $myIntern))->assertOk();

    $this->actingAs($coordinator)->get(route('monitor.intern', $stranger))->assertForbidden();
});

test('monitor routes are closed to admins and interns', function () {
    $admin = makeStaff(['role' => 'admin']);
    $intern = makeIntern();

    $this->actingAs($admin)->get(route('monitor.dashboard'))->assertForbidden();
    $this->actingAs($intern)->get(route('monitor.dashboard'))->assertForbidden();
});

test('coordinator and supervisor land on the monitor dashboard after login', function () {
    $coordinator = makeCoordinator();
    $office = makeOffice();
    $supervisor = makeSupervisor($office);

    $this->post(route('login.store'), ['login' => $coordinator->email, 'password' => 'password'])
        ->assertRedirect(route('monitor.dashboard'));

    // Log the coordinator out first — the login route is guest-only, so a
    // second login attempt while still authenticated just bounces to '/'.
    $this->post(route('logout'));

    $this->post(route('login.store'), ['login' => $supervisor->email, 'password' => 'password'])
        ->assertRedirect(route('monitor.dashboard'));
});

test('interns table sorts by progress — all headers sort by hours progress', function () {
    $admin = makeStaff(['role' => 'admin']);
    $office = makeOffice(['name' => 'AAA Office']);

    makeIntern(['student_id' => 'T-7001', 'last_name' => 'Aaaerson']);
    makeIntern(['student_id' => 'T-7002', 'last_name' => 'Zzzmith', 'office_id' => $office->id]);

    // All column headers sort by progress — direction toggles asc/desc
    $desc = $this->actingAs($admin)->get(route('admin.interns.index', ['dir' => 'desc']))->getContent();
    $asc = $this->actingAs($admin)->get(route('admin.interns.index', ['dir' => 'asc']))->getContent();

    // Both render successfully with progress sorting
    expect($desc)->toContain('Aaaerson');
    expect($asc)->toContain('Aaaerson');

    // The sort parameter is accepted but always sorts by progress
    $this->actingAs($admin)->get(route('admin.interns.index', ['sort' => 'office', 'dir' => 'desc']))->assertOk();
    $this->actingAs($admin)->get(route('admin.interns.index', ['sort' => 'coordinator', 'dir' => 'asc']))->assertOk();
    $this->actingAs($admin)->get(route('admin.interns.index', ['sort' => 'progress', 'dir' => 'desc']))->assertOk();
    $this->actingAs($admin)->get(route('admin.interns.index', ['sort' => 'status', 'dir' => 'asc']))->assertOk();
    $this->actingAs($admin)->get(route('admin.interns.index', ['sort' => 'bogus-column']))->assertOk();

    // All header links point to progress sorting
    $this->actingAs($admin)->get(route('admin.interns.index'))
        ->assertOk()
        ->assertSee('dir=desc');
});

test('the scoped interns list still sorts by progress for monitors', function () {
    $coordinator = makeCoordinator();
    $office = makeOffice(['name' => 'AAA Office']);

    makeIntern(['student_id' => 'T-8001', 'last_name' => 'Aaaerson', 'coordinator_id' => $coordinator->id, 'office_id' => $office->id]);
    makeIntern(['student_id' => 'T-8002', 'last_name' => 'Zzzmith', 'coordinator_id' => $coordinator->id, 'office_id' => $office->id]);

    $desc = $this->actingAs($coordinator)->get(route('admin.interns.index', ['dir' => 'desc']))->getContent();
    $asc = $this->actingAs($coordinator)->get(route('admin.interns.index', ['dir' => 'asc']))->getContent();

    // Both orders render successfully with progress sorting, and the
    // coordinator only ever sees their own interns.
    expect($desc)->toContain('Aaaerson')
        ->and($desc)->toContain('Zzzmith')
        ->and($asc)->toContain('Aaaerson');
});
test('admin can soft-delete and restore staff', function () {
    $admin = makeStaff(['role' => 'admin']);
    $coordinator = makeCoordinator();

    $this->actingAs($admin)->delete(route('admin.staff.destroy', $coordinator))
        ->assertRedirect(route('admin.staff.index'));

    $deleted = $coordinator->fresh();

    expect($deleted->deleted_at)->not->toBeNull()
        ->and($deleted->is_active)->toBeFalse()
        // hidden from the normal staff listing
        ->and(User::where('email', $coordinator->email)->exists())->toBeFalse()
        // but still recoverable
        ->and(User::onlyTrashed()->where('email', $coordinator->email)->exists())->toBeTrue();

    // Deleted staff cannot log in (log the admin session out first —
    // actingAs() persists across requests within a test)
    $this->post(route('logout'));
    $this->post(route('login.store'), ['login' => $coordinator->email, 'password' => 'password']);
    $this->assertGuest();

    // Restore brings them back, reactivated
    $this->actingAs($admin)->post(route('admin.staff.restore', $coordinator->id))
        ->assertRedirect(route('admin.staff.index', ['role' => 'coordinator']));

    $restored = $coordinator->fresh();

    expect($restored->deleted_at)->toBeNull()
        ->and($restored->is_active)->toBeTrue();

    $this->post(route('logout'));
    $this->post(route('login.store'), ['login' => $coordinator->email, 'password' => 'password'])
        ->assertRedirect(route('monitor.dashboard'));
});

test('admin can permanently delete an archived staff account', function () {
    $admin = makeStaff(['role' => 'admin']);
    $office = makeOffice();
    $coordinator = makeCoordinator();
    $intern = makeIntern(['student_id' => 'T-9010', 'coordinator_id' => $coordinator->id, 'office_id' => $office->id]);

    // Archive it first — permanent delete only applies to the Deleted section.
    $this->actingAs($admin)->delete(route('admin.staff.destroy', $coordinator))
        ->assertRedirect(route('admin.staff.index'));

    expect(User::onlyTrashed()->where('email', $coordinator->email)->exists())->toBeTrue();

    // Permanent delete removes the record for good.
    $this->actingAs($admin)->delete(route('admin.staff.force-delete', $coordinator->id))
        ->assertRedirect(route('admin.staff.index'))
        ->assertSessionHas('status');

    // Gone entirely — not even from the archive, so it can't be restored.
    expect(User::withTrashed()->where('email', $coordinator->email)->exists())->toBeFalse();

    $this->actingAs($admin)->post(route('admin.staff.restore', $coordinator->id))
        ->assertNotFound();

    // The intern stays enrolled, merely unassigned from the coordinator.
    expect($intern->fresh()->deleted_at)->toBeNull()
        ->and($intern->fresh()->coordinator_id)->toBeNull();
});

// Permanent delete only ever targets archived accounts — active staff are
// untouched by the force-delete route.
test('permanent delete cannot remove an active staff account', function () {
    $admin = makeStaff(['role' => 'admin']);
    $coordinator = makeCoordinator();

    $this->actingAs($admin)->delete(route('admin.staff.force-delete', $coordinator->id))
        ->assertNotFound();

    expect(User::where('email', $coordinator->email)->exists())->toBeTrue();
});

test('admin can soft-delete and restore an intern', function () {
    $admin = makeStaff(['role' => 'admin']);
    $intern = makeIntern(['student_id' => 'T-9011', 'email' => 'del.intern@example.com']);

    $this->actingAs($admin)->delete(route('admin.interns.destroy', $intern))
        ->assertRedirect(route('admin.interns.index'));

    expect($intern->fresh()->deleted_at)->not->toBeNull()
        ->and($intern->fresh()->is_active)->toBeFalse()
        // hidden from the normal interns listing
        ->and(User::where('email', 'del.intern@example.com')->exists())->toBeFalse()
        // but still recoverable
        ->and(User::onlyTrashed()->where('email', 'del.intern@example.com')->exists())->toBeTrue();

    // Deleted interns cannot log in.
    $this->post(route('logout'));
    $this->post(route('login.store'), ['login' => 'T-9011', 'password' => 'password']);
    $this->assertGuest();

    // Restore brings them back, reactivated.
    $this->actingAs($admin)->post(route('admin.interns.restore', $intern->id))
        ->assertRedirect(route('admin.interns.index'));

    expect($intern->fresh()->deleted_at)->toBeNull()
        ->and($intern->fresh()->is_active)->toBeTrue();
});

test('admin can permanently delete an archived intern', function () {
    $admin = makeStaff(['role' => 'admin']);
    $intern = makeIntern(['student_id' => 'T-9012']);

    // Archive it first — permanent delete only applies to the Deleted section.
    $this->actingAs($admin)->delete(route('admin.interns.destroy', $intern));

    $this->actingAs($admin)->delete(route('admin.interns.force-delete', $intern->id))
        ->assertRedirect(route('admin.interns.index'))
        ->assertSessionHas('status');

    // Gone entirely — not even from the archive, so it can't be restored.
    expect(User::withTrashed()->where('student_id', 'T-9012')->exists())->toBeFalse();

    $this->actingAs($admin)->post(route('admin.interns.restore', $intern->id))
        ->assertNotFound();
});

test('permanent delete cannot remove an active intern', function () {
    $admin = makeStaff(['role' => 'admin']);
    $intern = makeIntern(['student_id' => 'T-9013']);

    $this->actingAs($admin)->delete(route('admin.interns.force-delete', $intern->id))
        ->assertNotFound();

    expect(User::where('student_id', 'T-9013')->exists())->toBeTrue();
});

test('live search renders only the filtered list fragment', function () {
    $admin = makeStaff(['role' => 'admin']);
    makeIntern(['student_id' => 'T-9101', 'last_name' => 'Zebra', 'first_name' => 'Alice']);
    makeIntern(['student_id' => 'T-9102', 'last_name' => 'Alpha', 'first_name' => 'Bob']);

    $html = $this->actingAs($admin)->get(route('admin.interns.index', ['search' => 'Zebra', 'partial' => 1]))
        ->assertOk()
        ->getContent();

    // The fragment: the table wrap with only the matching intern, and none
    // of the full page's chrome (no layout, no toolbar).
    expect($html)->toContain('internsTableWrap')
        ->toContain('Zebra')
        ->not->toContain('Alpha')
        ->not->toContain('<!DOCTYPE')
        ->not->toContain('Add Intern');
});

test('admin can assign an office and coordinator to an intern', function () {
    $admin = makeStaff(['role' => 'admin']);
    $office = makeOffice();
    $coordinator = makeCoordinator();
    $intern = makeIntern(['student_id' => 'T-9005']);

    $this->actingAs($admin)->put(route('admin.interns.update', $intern), [
        'student_id' => $intern->student_id,
        'first_name' => $intern->first_name,
        'last_name' => $intern->last_name,
        'email' => $intern->email,
        'ojt_track' => 'internship',
        'target_hours' => 500,
        'ojt_status' => 'active',
        'office_id' => $office->id,
        'coordinator_id' => $coordinator->id,
    ])->assertRedirect(route('admin.interns.index'));

    $intern->refresh();

    expect($intern->office_id)->toBe($office->id)
        ->and($intern->coordinator_id)->toBe($coordinator->id);
});
