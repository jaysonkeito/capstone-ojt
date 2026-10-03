<?php

use App\Models\InternPersonalInfo;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

/*
 * The intern full-profile page and the Student ID field on the edit form:
 * System Admins can view everything on file for one intern and correct a
 * mistyped Student ID without recreating the account.
 */

test('admin can view an intern\'s full profile page', function () {
    $admin = makeStaff(['role' => 'admin']);
    $office = makeOffice();
    $coordinator = makeCoordinator();
    $intern = makeIntern([
        'student_id' => '202300111',
        'department' => 'BSIT',
        'year_level' => 4,
        'batch' => '2026 Summer Batch A',
        'office_id' => $office->id,
        'coordinator_id' => $coordinator->id,
    ]);
    $enrollment = makeActiveEnrollment($intern);
    makeLog($intern, $enrollment, times: fullDutyTimes());

    InternPersonalInfo::create([
        'user_id' => $intern->id,
        'middle_name' => 'Tapia',
        'birthdate' => '2004-03-14',
        'sex' => 'Female',
        'present_address' => 'Bayawan City',
        'present_contact' => '09171234567',
        'father_name' => 'Rico Fathers',
    ]);

    $this->actingAs($admin)->get(route('admin.interns.show', $intern))
        ->assertOk()
        ->assertSee($intern->full_name)
        ->assertSee('202300111')
        ->assertSee('Bachelor of Science in Information Technology')
        ->assertSee('2026 Summer Batch A')
        ->assertSee($office->name)
        ->assertSee($coordinator->full_name)
        ->assertSee('Tapia')
        ->assertSee('Mar 14, 2004')
        ->assertSee('09171234567')
        ->assertSee('Internship OJT');
});

test('the profile page shows em-dashes for interns with no personal information yet', function () {
    $admin = makeStaff(['role' => 'admin']);
    $intern = makeIntern(['student_id' => 'T-7777']);

    $this->actingAs($admin)->get(route('admin.interns.show', $intern))
        ->assertOk()
        ->assertSee('No personal information on file yet');
});

test('the profile page is admin-only', function () {
    // Guest check first — actingAs() persists across requests within a test.
    $intern = makeIntern(['student_id' => 'T-7777']);

    $this->get(route('admin.interns.show', $intern))->assertRedirect(route('login'));

    $this->actingAs($intern)->get(route('admin.interns.show', $intern))->assertForbidden();
});

test('the profile page 404s for non-intern users', function () {
    $admin = makeStaff(['role' => 'admin']);
    $coordinator = makeCoordinator();

    $this->actingAs($admin)->get(route('admin.interns.show', $coordinator))->assertNotFound();
});

test('admin can edit an intern\'s student id', function () {
    $admin = makeStaff(['role' => 'admin']);
    $intern = makeIntern(['student_id' => '202300999']);

    $this->actingAs($admin)->put(route('admin.interns.update', $intern), [
        'student_id' => '202300001',
        'first_name' => $intern->first_name,
        'last_name' => $intern->last_name,
        'email' => $intern->email,
        'ojt_track' => 'internship',
        'target_hours' => 500,
        'ojt_status' => 'active',
    ])->assertRedirect(route('admin.interns.index'));

    expect($intern->fresh()->student_id)->toBe('202300001');
});

test('a corrected student id cannot collide with another intern\'s', function () {
    $admin = makeStaff(['role' => 'admin']);
    $other = makeIntern(['student_id' => '202300111']);
    $intern = makeIntern(['student_id' => '202300999']);

    $this->actingAs($admin)->put(route('admin.interns.update', $intern), [
        'student_id' => $other->student_id,
        'first_name' => $intern->first_name,
        'last_name' => $intern->last_name,
        'email' => $intern->email,
        'ojt_track' => 'internship',
        'target_hours' => 500,
        'ojt_status' => 'active',
    ])->assertSessionHasErrors('student_id');

    expect($intern->fresh()->student_id)->toBe('202300999');
});

test('the edit form shows the student id field with its validation error', function () {
    $admin = makeStaff(['role' => 'admin']);
    $intern = makeIntern(['student_id' => '202300999']);

    $this->actingAs($admin)->get(route('admin.interns.edit', $intern))
        ->assertOk()
        ->assertSee('name="student_id"', false)
        ->assertSee(route('admin.interns.show', $intern));

    // A missing Student ID bounces back to the form with an error parked
    // in the session. (The array session driver keeps the same store alive
    // across requests in a test, so the flashed bag is asserted directly
    // instead of through a redirect-then-fetch round-trip.)
    $this->actingAs($admin)->put(route('admin.interns.update', $intern), [
        'student_id' => '',
        'first_name' => $intern->first_name,
        'last_name' => $intern->last_name,
        'email' => $intern->email,
        'ojt_track' => 'internship',
        'target_hours' => 500,
        'ojt_status' => 'active',
    ])->assertSessionHasErrors('student_id');

    // The form re-renders the error next to the field — seed the flashed
    // error bag in the raw session format the framework's JSON session
    // serialization stores it in (the session start-up step marshals it
    // back into a ViewErrorBag for the view).
    $this->actingAs($admin)->withSession(['errors' => [
        'default' => [
            'format' => ':message',
            'messages' => ['student_id' => ['The student id field is required.']],
        ],
    ]])->get(route('admin.interns.edit', $intern))
        ->assertOk()
        ->assertSee('The student id field is required');
});

test('the interns list links to the profile page', function () {
    $admin = makeStaff(['role' => 'admin']);
    $intern = makeIntern(['student_id' => 'T-7777']);

    $this->actingAs($admin)->get(route('admin.interns.index'))
        ->assertOk()
        ->assertSee(route('admin.interns.show', $intern));
});

test('editing other intern fields still works after the student id change', function () {
    $admin = makeStaff(['role' => 'admin']);
    $office = makeOffice();
    $coordinator = makeCoordinator();
    $intern = makeIntern(['student_id' => '202300999', 'department' => 'BSCS']);

    $this->actingAs($admin)->put(route('admin.interns.update', $intern), [
        'student_id' => '202300999',
        'first_name' => 'Juan',
        'last_name' => 'Dela Cruz',
        'email' => $intern->email,
        'department' => 'BSIT',
        'year_level' => '3',
        'batch' => 'Batch B',
        'ojt_track' => 'internship',
        'target_hours' => 500,
        'ojt_status' => 'active',
        'office_id' => $office->id,
        'coordinator_id' => $coordinator->id,
    ])->assertRedirect(route('admin.interns.index'));

    $intern->refresh();

    expect($intern->student_id)->toBe('202300999')
        ->and($intern->first_name)->toBe('Juan')
        ->and($intern->last_name)->toBe('Dela Cruz')
        ->and($intern->department)->toBe('BSIT')
        ->and($intern->year_level)->toBe(3)
        ->and($intern->batch)->toBe('Batch B')
        ->and($intern->office_id)->toBe($office->id)
        ->and($intern->coordinator_id)->toBe($coordinator->id);
});
