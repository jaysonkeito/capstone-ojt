<?php

use App\Models\Office;
use App\Models\User;
use App\Models\UserRole;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

function multiRoleStaff(string $primary, array $extra, array $attributes = []): User
{
    $staff = makeStaff(['role' => $primary, 'profile_completed_at' => now(), ...$attributes]);

    foreach ($extra as $role) {
        UserRole::create(['user_id' => $staff->id, 'role' => $role]);
    }

    return $staff->refresh();
}

it('holds the primary role plus every extra role from the pivot', function () {
    $staff = multiRoleStaff('supervisor', ['coordinator'], ['office_id' => Office::create(['name' => 'MIS Office', 'type' => 'internal'])->id]);

    expect($staff->role)->toBe('supervisor')
        ->and($staff->isSupervisor())->toBeTrue()
        ->and($staff->isCoordinator())->toBeTrue()
        ->and($staff->allRoles())->toBe(['supervisor', 'coordinator']);
});

it('lets the System Admin create a multi-role account with a priority role', function () {
    $admin = makeStaff(['role' => 'admin']);
    $office = Office::create(['name' => 'Registrar Office', 'type' => 'internal']);

    $response = $this->actingAs($admin)->post(route('admin.staff.store'), [
        'roles' => ['supervisor', 'coordinator'],
        'primary_role' => 'supervisor',
        'first_name' => 'Jesus',
        'last_name' => 'Sumadia',
        'email' => 'sumadia@norsu.edu.ph',
        'password' => 'Password123!',
        'office_id' => $office->id,
        'college_code' => 'cas',
        'is_active' => true,
    ]);

    $response->assertRedirect(route('admin.staff.index'));

    $staff = User::where('email', 'sumadia@norsu.edu.ph')->first();

    expect($staff->role)->toBe('supervisor')
        ->and($staff->roles->pluck('role')->sort()->values()->all())->toBe(['coordinator', 'supervisor'])
        ->and($staff->isCoordinator())->toBeTrue();
});

it('lets the System Admin re-role an existing staff account', function () {
    $admin = makeStaff(['role' => 'admin']);
    $office = Office::create(['name' => 'Alumni Office', 'type' => 'internal']);
    $staff = makeStaff(['role' => 'supervisor', 'office_id' => $office->id, 'approved_at' => now(), 'is_active' => true]);

    $this->actingAs($admin)->put(route('admin.staff.update', $staff), [
        'roles' => ['chair', 'supervisor'],
        'primary_role' => 'chair',
        'first_name' => $staff->first_name,
        'last_name' => $staff->last_name,
        'email' => $staff->email,
        'office_id' => $office->id,
        'college_code' => 'cas',
        'is_active' => true,
    ])->assertRedirect(route('admin.staff.index'));

    $staff->refresh();

    expect($staff->role)->toBe('chair')
        ->and($staff->isSupervisor())->toBeTrue()
        ->and($staff->roles->pluck('role')->sort()->values()->all())->toBe(['chair', 'supervisor']);
});

it('rejects a priority role outside the selected roles', function () {
    $admin = makeStaff(['role' => 'admin']);
    $office = Office::create(['name' => 'Guidance Office', 'type' => 'internal']);

    $this->actingAs($admin)->post(route('admin.staff.store'), [
        'roles' => ['supervisor'],
        'primary_role' => 'coordinator',
        'first_name' => 'Mismatch',
        'last_name' => 'Tester',
        'email' => 'mismatch@x.test',
        'password' => 'Password123!',
        'office_id' => $office->id,
        'college_code' => 'cas',
    ])->assertInvalid('primary_role');
});

it('requires an office when the supervisor hat is selected', function () {
    $admin = makeStaff(['role' => 'admin']);

    $this->actingAs($admin)->post(route('admin.staff.store'), [
        'roles' => ['coordinator', 'supervisor'],
        'primary_role' => 'coordinator',
        'first_name' => 'No',
        'last_name' => 'Office',
        'email' => 'nooffice@x.test',
        'password' => 'Password123!',
        'college_code' => 'cas',
    ])->assertInvalid('office_id');
});

it('requires a college when a college role is selected', function () {
    $admin = makeStaff(['role' => 'admin']);
    $office = Office::create(['name' => 'Collegeless Office', 'type' => 'internal']);

    $this->actingAs($admin)->post(route('admin.staff.store'), [
        'roles' => ['supervisor', 'chair'],
        'primary_role' => 'supervisor',
        'first_name' => 'No',
        'last_name' => 'College',
        'email' => 'nocollege@x.test',
        'password' => 'Password123!',
        'office_id' => $office->id,
    ])->assertInvalid('college_code');
});

it('admits a multi-role account to both roles\' routes', function () {
    $office = Office::create(['name' => 'Kiosk Office', 'type' => 'internal']);

    // Supervisor-primary + coordinator-extra passes the coordinator-only
    // staff management page.
    $supervisorHat = multiRoleStaff('supervisor', ['coordinator'], ['office_id' => $office->id]);
    $this->actingAs($supervisorHat)->get(route('admin.staff.index'))->assertOk();

    // Coordinator-primary + supervisor-extra passes the supervisor-only
    // manual log form.
    $coordinatorHat = multiRoleStaff('coordinator', ['supervisor'], ['office_id' => $office->id]);
    $this->actingAs($coordinatorHat)->get(route('monitor.logs.create'))->assertOk();
});

it('unions each role\'s interns for a coordinator-supervisor', function () {
    $office = Office::create(['name' => 'Union Office', 'type' => 'internal']);
    $staff = multiRoleStaff('coordinator', ['supervisor'], ['office_id' => $office->id]);

    $assigned = makeIntern(['student_id' => 'T-9101', 'coordinator_id' => $staff->id, 'last_name' => 'Assigned']);
    $placed = makeIntern(['student_id' => 'T-9102', 'office_id' => $office->id, 'last_name' => 'Placed']);
    $stranger = makeIntern(['student_id' => 'T-9103', 'last_name' => 'Stranger']);

    expect($staff->mayAccess($assigned))->toBeTrue()
        ->and($staff->mayAccess($placed))->toBeTrue()
        ->and($staff->mayAccess($stranger))->toBeFalse();
});

it('shows every held role on the staff index', function () {
    $admin = makeStaff(['role' => 'admin']);
    $office = Office::create(['name' => 'Badge Office', 'type' => 'internal']);
    $staff = multiRoleStaff('dean', ['supervisor'], [
        'first_name' => 'Badge',
        'last_name' => 'Wearer',
        'office_id' => $office->id,
        'approved_at' => now(),
        'is_active' => true,
    ]);

    $this->actingAs($admin)->get(route('admin.staff.index'))
        ->assertOk()
        ->assertSee('College Dean')
        ->assertSee('Supervisor')
        ->assertSee('Badge Wearer');
});

it('keeps interns and admins out of the pivot semantics', function () {
    $intern = makeIntern();

    expect($intern->hasRole('coordinator'))->toBeFalse()
        ->and($intern->allRoles())->toBe(['intern']);
});
