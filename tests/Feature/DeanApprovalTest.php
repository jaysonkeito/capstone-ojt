<?php

use App\Models\College;
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
