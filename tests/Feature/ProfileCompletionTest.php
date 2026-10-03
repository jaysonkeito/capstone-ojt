<?php

use App\Models\InternPersonalInfo;
use App\Models\StaffProfile;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

uses(RefreshDatabase::class);

/*
 * First-login profile completion: a self-service sign-up (an intern right
 * away, a coordinator/supervisor once the admin approves them) starts with
 * no profile_completed_at stamp, so its first sign-in lands on the profile
 * completion page and every dashboard stays locked until the form is saved
 * once. Saving stamps profile_completed_at and redirects to the dashboard.
 * Admin-provisioned accounts are stamped completed at creation and existing
 * accounts were backfilled, so the gate only ever touches fresh sign-ups.
 *
 * Helpers are local to this file so it runs standalone (test files define
 * their own factories rather than sharing them).
 */

/**
 * An intern with an unlocked (already completed) profile — the normal state
 * for admin-provisioned and pre-gate accounts.
 */
function completionIntern(array $attributes = []): User
{
    return User::create([
        'role' => 'intern',
        'ojt_track' => 'internship',
        'ojt_status' => 'active',
        'target_hours' => 500,
        'first_name' => 'Juan',
        'last_name' => 'Dela Cruz',
        'student_id' => '202300111',
        'email' => fake()->unique()->safeEmail(),
        'password' => 'password',
        'password_changed_at' => now(),
        'profile_completed_at' => now(),
        'is_active' => true,
        ...$attributes,
    ]);
}

/**
 * Self-register a coordinator through the public form (pending approval).
 */
function completionRegisterStaff(string $role = 'coordinator'): User
{
    $email = fake()->unique()->safeEmail();

    test()->post(route('register.store'), [
        'account_type' => $role,
        'first_name' => 'Maria',
        'last_name' => 'Santos',
        'username' => 'maria_santos_'.fake()->unique()->numberBetween(1, 9999),
        'email' => $email,
        'password' => 'DutyDay2026!',
        'password_confirmation' => 'DutyDay2026!',
        'agree_terms' => '1',
    ]);

    return User::where('email', $email)->firstOrFail();
}

test('a fresh intern sign-up is sent to profile completion on first sign-in and the dashboard stays locked', function () {
    // Self-register an intern — created without a completion stamp.
    $this->post(route('register.store'), [
        'account_type' => 'intern',
        'student_id' => '202300999',
        'first_name' => 'Fresh',
        'last_name' => 'Signup',
        'email' => 'fresh@example.com',
        'password' => 'DutyDay2026!',
        'password_confirmation' => 'DutyDay2026!',
        'agree_terms' => '1',
    ]);

    $intern = User::where('email', 'fresh@example.com')->firstOrFail();
    expect($intern->needsProfileCompletion())->toBeTrue();

    // First sign-in lands on the completion page, not the dashboard.
    $this->post(route('login.store'), ['login' => '202300999', 'password' => 'DutyDay2026!'])
        ->assertRedirect(route('profile-completion.edit'));

    // The completion page renders with the student sheet.
    $this->get(route('profile-completion.edit'))
        ->assertOk()
        ->assertSee('Student Information')
        ->assertSee('Complete Your Profile')
        ->assertDontSee('Instructor Information');

    // Every intern section (and the app root) bounce back until the form is
    // saved — the completion sheet is the only place they can go.
    $this->get(route('intern.dashboard'))->assertRedirect(route('profile-completion.edit'));
    $this->get(route('intern.my-qr'))->assertRedirect(route('profile-completion.edit'));
    $this->get(route('intern.documentation'))->assertRedirect(route('profile-completion.edit'));
    $this->get(route('intern.personal-information.edit'))->assertRedirect(route('profile-completion.edit'));
    $this->get(route('intern.requirements.index'))->assertRedirect(route('profile-completion.edit'));
    $this->get(route('profile'))->assertRedirect(route('profile-completion.edit'));
    $this->get('/')->assertRedirect(route('profile-completion.edit'));
});

test('saving the student profile unlocks the dashboard and persists the details', function () {
    Storage::fake('public');

    $intern = completionIntern(['profile_completed_at' => null]);

    $response = $this->actingAs($intern)->put(route('profile-completion.update'), [
        'username' => 'fresh_signup',
        'email' => $intern->email,
        'department' => 'BSIT',
        'year_level' => 3,
        'middle_name' => 'Pacificador',
        'sex' => 'Female',
        'birthdate' => '2004-05-10',
        'phone_number' => '09534559021',
        'institutional_email' => 'fresh.signup@norsu.edu.ph',
        'guardian_name' => 'Michelle Pacificador',
        'guardian_contact' => '09534559022',
        'present_address' => 'Sicopong, Poblacion, Santa Catalina',
        'facebook_link' => 'facebook.com/fresh.signup',
        'youtube_link' => 'youtube.com/@freshsignup',
        'linkedin_link' => 'linkedin.com/in/freshsignup',
        'photo' => UploadedFile::fake()->image('me.jpg'),
    ]);

    $response->assertRedirect(route('intern.dashboard'))
        ->assertSessionHas('status');

    $intern->refresh();

    // Completion stamp lifts the gate, account details land on users.
    expect($intern->profile_completed_at)->not->toBeNull()
        ->and($intern->needsProfileCompletion())->toBeFalse()
        ->and($intern->username)->toBe('fresh_signup')
        ->and($intern->department)->toBe('BSIT')
        ->and($intern->year_level)->toBe(3)
        ->and($intern->avatar_path)->not->toBeNull();

    // Student details land on the intern's personal info row.
    $info = InternPersonalInfo::where('user_id', $intern->id)->first();
    expect($info)->not->toBeNull()
        ->and($info->sex)->toBe('Female')
        ->and($info->birthdate->format('Y-m-d'))->toBe('2004-05-10')
        ->and($info->phone_number)->toBe('09534559021')
        ->and($info->institutional_email)->toBe('fresh.signup@norsu.edu.ph')
        ->and($info->guardian_name)->toBe('Michelle Pacificador')
        ->and($info->facebook_link)->toBe('facebook.com/fresh.signup')
        ->and($info->linkedin_link)->toBe('linkedin.com/in/freshsignup');

    Storage::disk('public')->assertExists($intern->avatar_path);

    // Every section unlocks after the single save.
    $this->get(route('intern.dashboard'))->assertOk();
    $this->get(route('intern.my-qr'))->assertOk();
    $this->get(route('intern.documentation'))->assertOk();
    $this->get(route('intern.personal-information.edit'))->assertOk();
    $this->get(route('intern.requirements.index'))->assertOk();
    $this->get(route('profile'))->assertOk();
});

test('the intern sheet can be saved nearly blank — one save is enough to pass the gate', function () {
    $intern = completionIntern(['profile_completed_at' => null]);

    $this->actingAs($intern)->put(route('profile-completion.update'), [
        'email' => $intern->email,
    ])->assertRedirect(route('intern.dashboard'));

    expect($intern->fresh()->profile_completed_at)->not->toBeNull();
});

test('an approved staff sign-up completes the instructor sheet before the monitor dashboard unlocks', function () {
    Storage::fake('public');

    $pending = completionRegisterStaff('coordinator');

    // Admin approves the pending sign-up.
    $admin = User::create([
        'role' => 'admin',
        'first_name' => 'Admin',
        'last_name' => 'User',
        'email' => fake()->unique()->safeEmail(),
        'password' => 'password',
        'is_active' => true,
    ]);
    $this->actingAs($admin)->post(route('admin.approvals.approve', $pending));
    auth()->logout();

    // First sign-in lands on the completion page; the dashboard is locked.
    $this->post(route('login.store'), ['login' => $pending->email, 'password' => 'DutyDay2026!'])
        ->assertRedirect(route('profile-completion.edit'));

    $this->get(route('profile-completion.edit'))
        ->assertOk()
        ->assertSee('Instructor Information')
        ->assertDontSee('Student Information');

    $this->get(route('monitor.dashboard'))->assertRedirect(route('profile-completion.edit'));

    // Saving the instructor sheet unlocks the monitor dashboard.
    $this->put(route('profile-completion.update'), [
        'username' => 'maria_santos',
        'first_name' => 'Maria',
        'last_name' => 'Santos',
        'email' => $pending->email,
        'prefix_title' => 'Ms.',
        'suffix_title' => null,
        'middle_name' => 'Reyes',
        'employee_id' => 'E-2024-001',
        'institutional_email' => 'maria.santos@norsu.edu.ph',
        'civil_status' => 'Single',
        'designation' => 'Instructor I',
        'date_hired' => '2019-06-10',
        'department' => 'College of Arts and Sciences',
        'gender' => 'Female',
        'mobile_number' => '09171234567',
        'qualification' => 'Master in Information Technology',
        'specialization' => 'Database Systems',
        'resume' => UploadedFile::fake()->create('resume.pdf', 200, 'application/pdf'),
    ])->assertRedirect(route('monitor.dashboard'));

    $pending->refresh();

    expect($pending->profile_completed_at)->not->toBeNull()
        ->and($pending->needsProfileCompletion())->toBeFalse()
        ->and($pending->username)->toBe('maria_santos');

    $profile = StaffProfile::where('user_id', $pending->id)->first();
    expect($profile)->not->toBeNull()
        ->and($profile->employee_id)->toBe('E-2024-001')
        ->and($profile->designation)->toBe('Instructor I')
        ->and($profile->date_hired->format('Y-m-d'))->toBe('2019-06-10')
        ->and($profile->mobile_number)->toBe('09171234567')
        ->and($profile->resume_path)->not->toBeNull();

    Storage::disk('public')->assertExists($profile->resume_path);

    $this->get(route('monitor.dashboard'))->assertOk();
});

test('accounts with a completed profile skip the completion page entirely', function () {
    $intern = completionIntern(); // stamped completed by default

    $this->post(route('login.store'), ['login' => $intern->student_id, 'password' => 'password'])
        ->assertRedirect(route('intern.dashboard'));

    $this->get('/')->assertRedirect(route('intern.dashboard'));
    $this->get(route('intern.dashboard'))->assertOk();
});

test('admins never complete profiles and the completion page is closed to them', function () {
    $admin = User::create([
        'role' => 'admin',
        'first_name' => 'Admin',
        'last_name' => 'User',
        'email' => fake()->unique()->safeEmail(),
        'password' => 'password',
        'is_active' => true,
    ]);

    expect($admin->needsProfileCompletion())->toBeFalse();

    $this->actingAs($admin)->get(route('profile-completion.edit'))->assertForbidden();
    $this->actingAs($admin)->put(route('profile-completion.update'), [])->assertForbidden();
});
