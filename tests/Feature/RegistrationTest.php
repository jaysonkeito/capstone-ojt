<?php

use App\Models\OjtEnrollment;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;

uses(RefreshDatabase::class);

/*
 * Self-service registration: interns, coordinators, and supervisors can sign
 * up themselves. Interns are active immediately (the admin fills in OJT
 * placement details afterwards) and start with their first OJT set;
 * coordinator and supervisor sign-ups wait for the System Admin's approval
 * and cannot sign in until an admin approves them. Sign-up never auto-logs-in
 * — a fresh account is sent to the sign-in page first.
 *
 * Passwords must be strong (upper + lower case, a number, a symbol, 8+ chars),
 * Student IDs are digits only, and no two accounts may share a Student ID or
 * a full name.
 */

test('the register page renders for guests', function () {
    $this->get(route('register'))
        ->assertOk()
        ->assertSee('Create your account')
        ->assertSee('Student ID');
});

test('an intern can register, is sent to sign in, and starts with a first OJT set', function () {
    $response = $this->post(route('register.store'), [
        'account_type' => 'intern',
        'student_id' => '202300524',
        'first_name' => 'Juan',
        'last_name' => 'Dela Cruz',
        'email' => 'juan@example.com',
        'password' => 'DutyDay2026!',
        'password_confirmation' => 'DutyDay2026!',
        'agree_terms' => '1',
    ]);

    $response->assertRedirect(route('login'))
        ->assertSessionHas('status');
    $this->assertGuest();

    $intern = User::where('email', 'juan@example.com')->first();

    expect($intern)->not->toBeNull()
        ->and($intern->role)->toBe('intern')
        ->and($intern->student_id)->toBe('202300524')
        ->and($intern->is_active)->toBeTrue()
        // Interns skip the approval gate — approved by construction.
        ->and($intern->approved_at)->not->toBeNull()
        ->and($intern->password_changed_at)->not->toBeNull()
        ->and(Hash::check('DutyDay2026!', $intern->password))->toBeTrue();

    // First OJT set was created and the mirror columns are in sync.
    $enrollment = OjtEnrollment::where('user_id', $intern->id)->first();
    expect($enrollment)->not->toBeNull()
        ->and($enrollment->status)->toBe('active')
        ->and($enrollment->target_hours)->toBe(500)
        ->and($intern->fresh()->ojt_status)->toBe('active');
});

test('a coordinator can register but stays pending until an admin approves', function () {
    $response = $this->post(route('register.store'), [
        'account_type' => 'coordinator',
        'username' => 'maria_santos',
        'first_name' => 'Maria',
        'last_name' => 'Santos',
        'email' => 'maria@example.com',
        'password' => 'DutyDay2026!',
        'password_confirmation' => 'DutyDay2026!',
        'agree_terms' => '1',
    ]);

    $response->assertRedirect(route('login'))
        ->assertSessionHas('status');
    $this->assertGuest();

    $coordinator = User::where('email', 'maria@example.com')->first();

    expect($coordinator)->not->toBeNull()
        ->and($coordinator->role)->toBe('coordinator')
        // The chosen username lands on the account, ready for their profile.
        ->and($coordinator->username)->toBe('maria_santos')
        ->and($coordinator->student_id)->toBeNull()
        // Held for the System Admin's approval — can't sign in yet.
        ->and($coordinator->is_active)->toBeFalse()
        ->and($coordinator->approved_at)->toBeNull()
        ->and(OjtEnrollment::where('user_id', $coordinator->id)->exists())->toBeFalse();
});

test('a staff sign-up without a username is rejected', function () {
    $this->post(route('register.store'), [
        'account_type' => 'coordinator',
        'first_name' => 'No',
        'last_name' => 'Username',
        'email' => 'nousername@example.com',
        'password' => 'DutyDay2026!',
        'password_confirmation' => 'DutyDay2026!',
        'agree_terms' => '1',
    ])->assertSessionHasErrors('username');

    $this->assertGuest();
    expect(User::where('email', 'nousername@example.com')->exists())->toBeFalse();
});

test('a duplicate username is rejected for staff sign-ups', function () {
    makeStaff(['username' => 'taken_handle', 'email' => 'first@example.com']);

    $this->post(route('register.store'), [
        'account_type' => 'supervisor',
        'username' => 'taken_handle',
        'first_name' => 'Taken',
        'last_name' => 'Handle',
        'email' => 'second@example.com',
        'password' => 'DutyDay2026!',
        'password_confirmation' => 'DutyDay2026!',
        'agree_terms' => '1',
    ])->assertSessionHasErrors('username');

    expect(User::where('email', 'second@example.com')->exists())->toBeFalse();
});

test('a supervisor can register but stays pending until an admin approves', function () {
    $this->post(route('register.store'), [
        'account_type' => 'supervisor',
        'username' => 'super_visor',
        'first_name' => 'Super',
        'last_name' => 'Visor',
        'email' => 'super@example.com',
        'password' => 'DutyDay2026!',
        'password_confirmation' => 'DutyDay2026!',
        'agree_terms' => '1',
    ])->assertRedirect(route('login'));

    $this->assertGuest();

    $supervisor = User::where('email', 'super@example.com')->first();
    expect($supervisor)->not->toBeNull()
        ->and($supervisor->role)->toBe('supervisor')
        ->and($supervisor->username)->toBe('super_visor')
        ->and($supervisor->is_active)->toBeFalse()
        ->and($supervisor->approved_at)->toBeNull();
});

test('an intern registration requires a student id', function () {
    $this->post(route('register.store'), [
        'account_type' => 'intern',
        'first_name' => 'No',
        'last_name' => 'Id',
        'email' => 'noid@example.com',
        'password' => 'DutyDay2026!',
        'password_confirmation' => 'DutyDay2026!',
        'agree_terms' => '1',
    ])->assertSessionHasErrors('student_id');

    $this->assertGuest();
    expect(User::where('email', 'noid@example.com')->exists())->toBeFalse();
});

test('a student id containing non-digits is rejected', function () {
    $this->post(route('register.store'), [
        'account_type' => 'intern',
        'student_id' => 'ABC12345',
        'first_name' => 'Letters',
        'last_name' => 'Inid',
        'email' => 'letters@example.com',
        'password' => 'DutyDay2026!',
        'password_confirmation' => 'DutyDay2026!',
        'agree_terms' => '1',
    ])->assertSessionHasErrors('student_id');

    $this->assertGuest();
    expect(User::where('email', 'letters@example.com')->exists())->toBeFalse();
});

test('a duplicate student id is rejected', function () {
    makeIntern(['student_id' => '202300524']);

    $this->post(route('register.store'), [
        'account_type' => 'intern',
        'student_id' => '202300524',
        'first_name' => 'Dup',
        'last_name' => 'Licate',
        'email' => 'dup@example.com',
        'password' => 'DutyDay2026!',
        'password_confirmation' => 'DutyDay2026!',
        'agree_terms' => '1',
    ])->assertSessionHasErrors('student_id');
});

test('a duplicate full name is rejected', function () {
    makeIntern(['first_name' => 'Juan', 'last_name' => 'Reyes', 'student_id' => '900000001']);

    $this->post(route('register.store'), [
        'account_type' => 'intern',
        'student_id' => '900000002',
        'first_name' => 'Juan',
        'last_name' => 'Reyes',
        'email' => 'another.juan@example.com',
        'password' => 'DutyDay2026!',
        'password_confirmation' => 'DutyDay2026!',
        'agree_terms' => '1',
    ])->assertSessionHasErrors('last_name');

    $this->assertGuest();
    expect(User::where('email', 'another.juan@example.com')->exists())->toBeFalse();
});

test('the full-name check ignores case', function () {
    makeCoordinator(['first_name' => 'Maria', 'last_name' => 'Santos']);

    $this->post(route('register.store'), [
        'account_type' => 'coordinator',
        'first_name' => 'maria',
        'last_name' => 'SANTOS',
        'email' => 'case.maria@example.com',
        'password' => 'DutyDay2026!',
        'password_confirmation' => 'DutyDay2026!',
        'agree_terms' => '1',
    ])->assertSessionHasErrors('last_name');
});

test('a duplicate email is rejected', function () {
    makeIntern(['student_id' => 'T-2001', 'email' => 'taken@example.com']);

    $this->post(route('register.store'), [
        'account_type' => 'coordinator',
        'first_name' => 'Email',
        'last_name' => 'Taken',
        'email' => 'taken@example.com',
        'password' => 'DutyDay2026!',
        'password_confirmation' => 'DutyDay2026!',
        'agree_terms' => '1',
    ])->assertSessionHasErrors('email');
});

test('the password must be confirmed', function () {
    $this->post(route('register.store'), [
        'account_type' => 'coordinator',
        'first_name' => 'Mis',
        'last_name' => 'Match',
        'email' => 'mismatch@example.com',
        'password' => 'DutyDay2026!',
        'password_confirmation' => 'DutyDay2027!',
        'agree_terms' => '1',
    ])->assertSessionHasErrors('password');

    $this->assertGuest();
});

test('the password must meet every complexity requirement', function () {
    // Each of these is missing exactly one class (or is too short), so every
    // one must be rejected with an error on the password field.
    $weakPasswords = [
        'abc',            // too short, no upper/number/symbol
        'lowercase123!',  // no uppercase
        'UPPERCASE123!',  // no lowercase
        'NoNumbers!!',    // no digit
        'NoSymbol2026',   // no symbol
        'Ab1!',           // has every class but under 8 characters
    ];

    foreach ($weakPasswords as $index => $weak) {
        $this->post(route('register.store'), [
            'account_type' => 'coordinator',
            'first_name' => 'Weak'.$index,
            'last_name' => 'Pass'.$index,
            'email' => "weak{$index}@example.com",
            'password' => $weak,
            'password_confirmation' => $weak,
            'agree_terms' => '1',
        ])->assertSessionHasErrors('password');
    }

    $this->assertGuest();
    expect(User::where('role', 'coordinator')->count())->toBe(0);
});

test('the terms must be accepted', function () {
    $this->post(route('register.store'), [
        'account_type' => 'coordinator',
        'first_name' => 'No',
        'last_name' => 'Terms',
        'email' => 'noterms@example.com',
        'password' => 'DutyDay2026!',
        'password_confirmation' => 'DutyDay2026!',
        // agree_terms missing
    ])->assertSessionHasErrors('agree_terms');

    expect(User::where('email', 'noterms@example.com')->exists())->toBeFalse();
});

test('an admin account cannot be self-registered', function () {
    $this->post(route('register.store'), [
        'account_type' => 'admin',
        'first_name' => 'Sneaky',
        'last_name' => 'Admin',
        'email' => 'sneaky@example.com',
        'password' => 'DutyDay2026!',
        'password_confirmation' => 'DutyDay2026!',
        'agree_terms' => '1',
    ])->assertSessionHasErrors('account_type');

    expect(User::where('email', 'sneaky@example.com')->exists())->toBeFalse();
});

test('a registered intern can immediately sign in, landing on profile completion', function () {
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

    // Sign-up never auto-logs-in, so the fresh account signs in normally —
    // and its first sign-in lands on the profile completion page (the
    // account was never stamped completed).
    $fresh = User::where('email', 'fresh@example.com')->firstOrFail();
    // Interns never submit a username at sign-up — they choose one later, on
    // the profile completion sheet, if they want one.
    expect($fresh->username)->toBeNull();

    $this->post(route('login.store'), [
        'login' => '202300999',
        'password' => 'DutyDay2026!',
    ])->assertRedirect(route('profile-completion.edit'));

    // Completing the profile unlocks the intern dashboard.
    $this->put(route('profile-completion.update'), [
        'username' => 'fresh_signup',
        'email' => 'fresh@example.com',
        'department' => 'BSIT',
        'year_level' => 3,
    ])->assertRedirect(route('intern.dashboard'));
});

/*
 * Live "already taken?" endpoint — powers the sign-up form's inline duplicate
 * checks for Student ID, email, and full name as the user types. It mirrors
 * the submit-time unique rules (soft-deleted accounts still count as taken).
 */

test('the availability endpoint flags a Student ID that is already registered', function () {
    makeIntern(['student_id' => '202300700']);

    $this->postJson(route('register.availability'), [
        'field' => 'student_id',
        'value' => '202300700',
    ])->assertOk()->assertExactJson(['taken' => true]);
});

test('the availability endpoint reports a free Student ID as available', function () {
    $this->postJson(route('register.availability'), [
        'field' => 'student_id',
        'value' => '999999999',
    ])->assertOk()->assertExactJson(['taken' => false]);
});

test('the availability endpoint flags an email that is already registered', function () {
    makeIntern(['student_id' => '202300701', 'email' => 'taken@example.com']);

    $this->postJson(route('register.availability'), [
        'field' => 'email',
        'value' => 'taken@example.com',
    ])->assertOk()->assertExactJson(['taken' => true]);
});

test('the availability endpoint reports a free email as available', function () {
    $this->postJson(route('register.availability'), [
        'field' => 'email',
        'value' => 'nobody@example.com',
    ])->assertOk()->assertExactJson(['taken' => false]);
});

test('the availability endpoint flags a username that is already registered', function () {
    makeStaff(['username' => 'taken_handle']);

    $this->postJson(route('register.availability'), [
        'field' => 'username',
        'value' => 'taken_handle',
    ])->assertOk()->assertExactJson(['taken' => true]);
});

test('the availability endpoint reports a free username as available', function () {
    $this->postJson(route('register.availability'), [
        'field' => 'username',
        'value' => 'free_handle',
    ])->assertOk()->assertExactJson(['taken' => false]);
});

test('the availability endpoint flags a full name that is already registered, case-insensitively', function () {
    makeCoordinator(['first_name' => 'Maria', 'last_name' => 'Santos']);

    $this->postJson(route('register.availability'), [
        'field' => 'full_name',
        'first_name' => 'maria',
        'last_name' => 'SANTOS',
    ])->assertOk()->assertExactJson(['taken' => true]);
});

test('the availability endpoint reports a full name as available when only one part matches', function () {
    makeCoordinator(['first_name' => 'Maria', 'last_name' => 'Santos']);

    $this->postJson(route('register.availability'), [
        'field' => 'full_name',
        'first_name' => 'Maria',
        'last_name' => 'Ramos',
    ])->assertOk()->assertExactJson(['taken' => false]);
});

test('the availability endpoint counts a soft-deleted account as taken', function () {
    $intern = makeIntern(['student_id' => '202300702', 'email' => 'gone@example.com']);
    $intern->delete();

    $this->postJson(route('register.availability'), [
        'field' => 'email',
        'value' => 'gone@example.com',
    ])->assertOk()->assertExactJson(['taken' => true]);
});

test('the availability endpoint treats a blank value as available', function () {
    $this->postJson(route('register.availability'), [
        'field' => 'student_id',
        'value' => '',
    ])->assertOk()->assertExactJson(['taken' => false]);
});

test('the availability endpoint rejects an unknown field', function () {
    $this->postJson(route('register.availability'), [
        'field' => 'password',
        'value' => 'whatever',
    ])->assertStatus(422);
});

test('a guest can reach the availability endpoint', function () {
    $this->postJson(route('register.availability'), [
        'field' => 'student_id',
        'value' => '12345',
    ])->assertOk();

    $this->assertGuest();
});
