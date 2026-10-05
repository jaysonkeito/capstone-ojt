<?php

use App\Models\College;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

/*
 * Self-service coordinator/supervisor sign-ups are held for the System
 * Admin's approval: the account starts inactive (it can't log in) and lands
 * in the admin's Account Approvals section. Approving activates it; rejecting
 * removes it for good so the person can re-apply. Interns register instantly
 * and never enter this queue.
 *
 * Helpers are local to this file so it runs standalone (test files define
 * their own factories rather than sharing them).
 */

/**
 * Submit a self-service staff sign-up through the public form and return the
 * freshly created (pending) account.
 */
function registerStaffAccount(string $role, array $attributes = []): User
{
    $first = $attributes['first_name'] ?? 'Juan';
    $last = $attributes['last_name'] ?? 'Applicant';
    $email = $attributes['email'] ?? fake()->unique()->safeEmail();
    $username = $attributes['username'] ?? strtolower($first).'_'.strtolower($last).fake()->unique()->numberBetween(1, 9999);

    test()->post(route('register.store'), [
        'account_type' => $role,
        'first_name' => $first,
        'last_name' => $last,
        'username' => $username,
        'email' => $email,
        // null (or absent) means the college-less external-office case
        'college_code' => array_key_exists('college_code', $attributes) ? $attributes['college_code'] : 'cas',
        'password' => 'DutyDay2026!',
        'password_confirmation' => 'DutyDay2026!',
        'agree_terms' => '1',
    ]);

    return User::where('email', $email)->firstOrFail();
}

/**
 * An admin-provisioned account — the gate never applies to these.
 */
function approvalUser(string $role, array $attributes = []): User
{
    return User::create([
        'role' => $role,
        'first_name' => $role === 'intern' ? 'Instant' : 'Active',
        'last_name' => $role === 'intern' ? 'Intern' : 'Staff',
        'email' => fake()->unique()->safeEmail(),
        'password' => 'password',
        'password_changed_at' => now(),
        'target_hours' => 0,
        'is_active' => true,
        'approved_at' => now(),
        'college_code' => 'cas',
        // Admin-provisioned accounts skip the profile completion wall.
        'profile_completed_at' => now(),
        ...$attributes,
    ]);
}

test('a pending staff sign-up cannot sign in until the admin approves it', function () {
    $pending = registerStaffAccount('coordinator', ['first_name' => 'Maria', 'last_name' => 'Santos']);

    // Still locked out while pending...
    $this->post(route('login.store'), [
        'login' => $pending->email,
        'password' => 'DutyDay2026!',
    ])->assertSessionHasErrors('login');
    $this->assertGuest();

    // ...and in right after the admin approves. Their first sign-in lands
    // on the profile completion page, not the dashboard.
    $admin = approvalUser('admin');
    $this->actingAs($admin)->post(route('admin.approvals.approve', $pending));
    auth()->logout();

    $this->post(route('login.store'), [
        'login' => $pending->email,
        'password' => 'DutyDay2026!',
    ])->assertRedirect(route('profile-completion.edit'));
    $this->assertAuthenticatedAs($pending->fresh());

    // Completing the profile unlocks the monitor dashboard.
    $this->put(route('profile-completion.update'), [
        'username' => 'maria_santos',
        'first_name' => 'Maria',
        'last_name' => 'Santos',
        'email' => $pending->email,
        'mobile_number' => '09171234567',
    ])->assertRedirect(route('monitor.dashboard'));

    expect($pending->fresh()->profile_completed_at)->not->toBeNull();
});

test('pending coordinator and supervisor sign-ups are listed on the approvals page', function () {
    registerStaffAccount('coordinator', ['first_name' => 'Maria', 'last_name' => 'Santos']);
    registerStaffAccount('supervisor', ['first_name' => 'Sofia', 'last_name' => 'Reyes']);

    $this->actingAs(approvalUser('admin'))->get(route('admin.approvals.index'))
        ->assertOk()
        ->assertSee('Santos, Maria')
        ->assertSee('Reyes, Sofia');
});

test('approving a pending sign-up activates it and moves it into Staff', function () {
    $pending = registerStaffAccount('coordinator', ['first_name' => 'Maria', 'last_name' => 'Santos']);

    $this->actingAs(approvalUser('admin'))
        ->post(route('admin.approvals.approve', $pending))
        ->assertRedirect(route('admin.approvals.index'));

    expect($pending->fresh()->is_active)->toBeTrue()
        ->and($pending->fresh()->approved_at)->not->toBeNull();

    // Gone from the queue, present under Staff.
    $this->get(route('admin.approvals.index'))->assertDontSee($pending->email);
    $this->get(route('admin.staff.index'))->assertSee($pending->email);
});

test('rejecting a pending sign-up removes it and frees the email for a new application', function () {
    $pending = registerStaffAccount('supervisor', ['first_name' => 'Sofia', 'last_name' => 'Reyes']);

    $this->actingAs(approvalUser('admin'))
        ->delete(route('admin.approvals.reject', $pending))
        ->assertRedirect(route('admin.approvals.index'));

    expect(User::where('email', $pending->email)->exists())->toBeFalse();

    // The same person can simply apply again (as a guest this time).
    auth()->logout();

    $reapplied = registerStaffAccount('supervisor', [
        'first_name' => 'Sofia',
        'last_name' => 'Reyes',
        'email' => $pending->email,
    ]);

    expect($reapplied->isPendingApproval())->toBeTrue();
});

test('approvals can only be approved or rejected while the account is actually pending', function () {
    $active = approvalUser('coordinator');

    $this->actingAs(approvalUser('admin'))
        ->post(route('admin.approvals.approve', $active))
        ->assertNotFound();

    $this->actingAs(approvalUser('admin'))
        ->delete(route('admin.approvals.reject', $active))
        ->assertNotFound();

    expect($active->fresh())->not->toBeNull();
});

test('a deactivated staff account does not return to the approvals queue', function () {
    $pending = registerStaffAccount('coordinator', ['first_name' => 'Maria', 'last_name' => 'Santos']);

    $this->actingAs(approvalUser('admin'))->post(route('admin.approvals.approve', $pending));

    // Admin later deactivates them from the Staff section.
    $pending->fresh()->update(['is_active' => false]);

    // No longer pending — they show as inactive staff, not as a new applicant.
    $this->get(route('admin.approvals.index'))->assertDontSee($pending->email);
    $this->get(route('admin.staff.index'))->assertSee($pending->email);
});

test('pending sign-ups are hidden from the Staff section until approved', function () {
    $pending = registerStaffAccount('coordinator', ['first_name' => 'Maria', 'last_name' => 'Santos']);

    $this->actingAs(approvalUser('admin'))->get(route('admin.staff.index'))->assertDontSee($pending->email);
});

test('intern sign-ups never appear in the approvals queue', function () {
    $this->post(route('register.store'), [
        'account_type' => 'intern',
        'student_id' => '202300777',
        'first_name' => 'Insta',
        'last_name' => 'Intern',
        'email' => 'insta@example.com',
        'password' => 'DutyDay2026!',
        'password_confirmation' => 'DutyDay2026!',
        'agree_terms' => '1',
    ])->assertRedirect(route('login'));

    $intern = User::where('email', 'insta@example.com')->first();

    expect($intern->is_active)->toBeTrue()
        ->and($intern->isPendingApproval())->toBeFalse();

    $this->actingAs(approvalUser('admin'))->get(route('admin.approvals.index'))->assertDontSee('insta@example.com');
});

test('a College Dean can approve coordinator and supervisor sign-ups of their college', function () {
    College::firstOrCreate(['code' => 'cas'], ['name' => 'College of Arts and Sciences']);
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

test('a coordinator can approve a supervisor sign-up but not a coordinator sign-up', function () {
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

test('the approvals section is closed to interns, supervisors, and cross-college staff', function () {
    $coordinator = approvalUser('coordinator');
    $supervisor = approvalUser('supervisor');
    $intern = approvalUser('intern');

    // Coordinators may now work the queue for their college...
    $this->actingAs($coordinator)->get(route('admin.approvals.index'))->assertOk();

    // ...but supervisors and interns never do, and another college is out of bounds.
    $this->actingAs($supervisor)->get(route('admin.approvals.index'))->assertForbidden();
    $this->actingAs($intern)->get(route('admin.approvals.index'))->assertForbidden();
});
