<?php

use App\Models\College;
use App\Models\LogRequest;
use App\Models\Office;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    College::firstOrCreate(['code' => 'cas'], ['name' => 'College of Arts and Sciences']);
    College::firstOrCreate(['code' => 'cba'], ['name' => 'College of Business Administration']);
});

/*
 * Supervisor sign-ups carry no college when the host office is external —
 * offices may accept interns from any college. Their applications sit in the
 * System Admin's queue alone; deans and coordinators only decide sign-ups
 * whose college matches theirs.
 */

function supervisorWithoutCollege(): User
{
    return registerStaffAccount('supervisor', ['first_name' => 'Ext', 'last_name' => 'External', 'college_code' => null]);
}

test('a supervisor sign-up without a college is invisible to deans and coordinators', function () {
    $dean = approvalUser('dean');
    $coordinator = approvalUser('coordinator');
    $external = supervisorWithoutCollege();

    $this->actingAs($dean)->get(route('admin.approvals.index'))->assertOk()->assertDontSee($external->email);
    $this->actingAs($coordinator)->get(route('admin.approvals.index'))->assertOk()->assertDontSee($external->email);
    $this->actingAs(approvalUser('admin'))->get(route('admin.approvals.index'))->assertOk()->assertSee($external->email);
});

test('deans and coordinators cannot approve a college-less supervisor sign-up', function () {
    $external = supervisorWithoutCollege();

    $this->actingAs(approvalUser('dean'))->post(route('admin.approvals.approve', $external))->assertForbidden();
    $this->actingAs(approvalUser('coordinator'))->post(route('admin.approvals.approve', $external))->assertForbidden();

    // Only the System Admin decides it.
    $this->actingAs(approvalUser('admin'))->post(route('admin.approvals.approve', $external))
        ->assertRedirect(route('admin.approvals.index'));
    expect($external->fresh()->is_active)->toBeTrue();
});

test('a College Dean can approve coordinator and supervisor sign-ups of their college', function () {
    $dean = approvalUser('dean');
    $coordinatorApp = registerStaffAccount('coordinator', ['first_name' => 'Nina', 'last_name' => 'Cole']);
    $supervisorApp = registerStaffAccount('supervisor', ['first_name' => 'Ned', 'last_name' => 'Supervisor']);

    $this->actingAs($dean)->post(route('admin.approvals.approve', $coordinatorApp))
        ->assertRedirect(route('admin.approvals.index'));
    expect($coordinatorApp->fresh()->is_active)->toBeTrue();

    $this->actingAs($dean)->post(route('admin.approvals.approve', $supervisorApp))
        ->assertRedirect(route('admin.approvals.index'));
    expect($supervisorApp->fresh()->is_active)->toBeTrue();
});

test('a coordinator cannot approve a coordinator sign-up but can approve a supervisor sign-up', function () {
    $coordinator = approvalUser('coordinator');
    $coordinatorApp = registerStaffAccount('coordinator', ['first_name' => 'Nina', 'last_name' => 'Cole']);
    $supervisorApp = registerStaffAccount('supervisor', ['first_name' => 'Ned', 'last_name' => 'Supervisor']);

    $this->actingAs($coordinator)->post(route('admin.approvals.approve', $coordinatorApp))
        ->assertForbidden();
    expect($coordinatorApp->fresh()->is_active)->toBeFalse();

    $this->actingAs($coordinator)->post(route('admin.approvals.approve', $supervisorApp))
        ->assertRedirect(route('admin.approvals.index'));
    expect($supervisorApp->fresh()->is_active)->toBeTrue();
});

test('a dean of another college cannot approve a sign-up', function () {
    College::firstOrCreate(['code' => 'cba'], ['name' => 'College of Business Administration']);
    $cbaDean = User::create([
        'role' => 'dean', 'first_name' => 'Other', 'last_name' => 'Dean',
        'email' => 'cbadean@norsubscojt.online', 'username' => 'cbadean',
        'password' => 'password123', 'college_code' => 'cba', 'target_hours' => 0,
        'is_active' => true, 'profile_completed_at' => now(),
    ]);

    $coordinatorApp = registerStaffAccount('coordinator', ['first_name' => 'Nina', 'last_name' => 'Cole']);

    $this->actingAs($cbaDean)->post(route('admin.approvals.approve', $coordinatorApp))
        ->assertForbidden();
    expect($coordinatorApp->fresh()->is_active)->toBeFalse();
});

test('a dean with an office monitors and decides that office interns like a supervisor', function () {
    College::firstOrCreate(['code' => 'cas'], ['name' => 'College of Arts and Sciences']);
    $office = Office::create(['name' => 'Dean Office OJT', 'type' => 'internal']);
    $dean = User::create([
        'role' => 'dean', 'first_name' => 'Jean', 'last_name' => 'Esparcia',
        'email' => 'deanoffice' . fake()->unique()->safeEmail(), 'username' => 'deanoffice' . fake()->unique()->numberBetween(1, 9999),
        'password' => 'password123', 'college_code' => 'cas', 'office_id' => $office->id,
        'target_hours' => 0, 'is_active' => true, 'profile_completed_at' => now(),
    ]);
    $intern = makeIntern(['office_id' => $office->id, 'coordinator_id' => null]);
    makeActiveEnrollment($intern);

    // Sees the office's interns on the monitor dashboard...
    $this->actingAs($dean)->get(route('monitor.dashboard'))->assertOk()->assertSee($intern->full_name);

    // ...and decides their attendance requests like the office supervisor.
    $request = LogRequest::create([
        'intern_id' => $intern->id,
        'type' => LogRequest::TYPE_CORRECTION,
        'date' => today()->toDateString(),
        'reason' => 'Scanner missed my AM in',
        'status' => 'pending',
    ]);
    $this->actingAs($dean)->post(route('monitor.requests.log.decide', $request), [
        'action' => 'approved',
    ])->assertRedirect();

    expect($request->fresh()->status)->toBe('approved');
});

test('a coordinator can still approve a supervisor sign-up that carries their college', function () {
    $coordinator = approvalUser('coordinator');
    $supervisor = registerStaffAccount('supervisor', ['college_code' => 'cas']);

    $this->actingAs($coordinator)->post(route('admin.approvals.approve', $supervisor))
        ->assertRedirect(route('admin.approvals.index'));

    expect($supervisor->fresh()->is_active)->toBeTrue();
});

test('the staff form saves a supervisor without a college', function () {
    $admin = approvalUser('admin');
    $supervisor = makeStaff(['role' => 'supervisor']);

    $this->actingAs($admin)->put(route('admin.staff.update', $supervisor), [
        'first_name' => 'Ext',
        'last_name' => 'External',
        'email' => $supervisor->email,
        'office_id' => $supervisor->office_id,
        'college_code' => '',
        'is_active' => true,
    ])->assertRedirect(route('admin.staff.index'));

    expect($supervisor->fresh()->collegeCode())->toBeNull();
});
