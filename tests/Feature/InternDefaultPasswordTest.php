<?php

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;

uses(RefreshDatabase::class);

/*
 * The intern default-password policy: an intern provisioned by the admin
 * (or reset by them) logs in with their last name — in ANY casing the
 * intern types it (ALL CAPS, lowercase, mixed), because the login form
 * normalizes a case-insensitive last-name match to the stored casing
 * before the hash check. The stored hash must always match the CURRENT
 * last name; `ojt:sync-intern-passwords` re-anchors defaults when a last
 * name is corrected after provisioning. Interns who picked their own
 * password are never touched.
 *
 * Reuses the global helpers makeIntern / makeStaff defined in the sibling
 * feature tests.
 */

function provisionInternViaAdmin(): User
{
    $admin = makeStaff(['role' => 'admin']);

    test()->actingAs($admin)->post(route('admin.interns.store'), [
        'student_id' => '202311111',
        'first_name' => 'Juan',
        'last_name' => 'Santos',
        'email' => 'juan.santos@example.com',
        'ojt_track' => 'internship',
        'target_hours' => 500,
        'ojt_status' => 'active',
    ])->assertRedirect(route('admin.interns.index'));

    return User::where('student_id', '202311111')->firstOrFail();
}

test('an admin-provisioned intern logs in with their last name in any casing', function (string $typed) {
    $intern = provisionInternViaAdmin();

    expect($intern->password_changed_at)->toBeNull()
        ->and(Hash::check('Santos', $intern->password))->toBeTrue();

    $this->post(route('logout'));

    $this->post(route('login.store'), ['login' => '202311111', 'password' => $typed])
        ->assertRedirect(route('intern.dashboard'));
})->with([
    'as stored' => 'Santos',
    'ALL CAPS' => 'SANTOS',
    'all lowercase' => 'santos',
    'mixed casing' => 'sAnToS',
]);

test('a custom initial password on the create form counts as already changed', function () {
    $admin = makeStaff(['role' => 'admin']);

    $this->actingAs($admin)->post(route('admin.interns.store'), [
        'student_id' => '202311112',
        'first_name' => 'Maria',
        'last_name' => 'Lopez',
        'email' => 'maria.lopez@example.com',
        'ojt_track' => 'internship',
        'target_hours' => 500,
        'ojt_status' => 'active',
        'password' => 'Custom123',
    ])->assertRedirect(route('admin.interns.index'));

    $intern = User::where('student_id', '202311112')->firstOrFail();

    expect(Hash::check('Custom123', $intern->password))->toBeTrue()
        ->and(Hash::check('Lopez', $intern->password))->toBeFalse()
        // A custom password is treated as the intern's own — no default to sync.
        ->and($intern->password_changed_at)->not->toBeNull();
});

test('resetting a password restores the last-name default, login works in any casing', function () {
    $admin = makeStaff(['role' => 'admin']);
    $intern = makeIntern(['last_name' => 'Reyes', 'password' => 'Chosen2026', 'password_changed_at' => now()]);

    $this->actingAs($admin)
        ->post(route('admin.interns.reset-password', $intern))
        ->assertRedirect()
        ->assertSessionHas('status');

    $intern->refresh();

    expect(Hash::check('Reyes', $intern->password))->toBeTrue()
        ->and($intern->password_changed_at)->toBeNull();

    $this->post(route('logout'));

    $this->post(route('login.store'), ['login' => $intern->student_id, 'password' => 'REYES'])
        ->assertRedirect(route('intern.dashboard'));
});

test('sync command re-anchors a drifted default after a last name correction', function () {
    $intern = makeIntern(['last_name' => 'Santos']);
    $intern->update(['password' => 'Santos', 'password_changed_at' => null]);
    expect(Hash::check('Santos', $intern->fresh()->password))->toBeTrue();

    // The roster correction: the last name changes after provisioning, so
    // the stored hash no longer matches the current name and the intern
    // can no longer log in.
    $intern->update(['last_name' => 'Santiago']);
    expect(Hash::check('Santiago', $intern->fresh()->password))->toBeFalse();

    $this->artisan('ojt:sync-intern-passwords')
        ->expectsOutputToContain($intern->student_id)
        ->expectsOutputToContain('1 intern(s) reset')
        ->assertSuccessful();

    expect(Hash::check('Santiago', $intern->fresh()->password))->toBeTrue()
        // Still flagged as the default, so the intern gets the change-it nudge.
        ->and($intern->fresh()->password_changed_at)->toBeNull();

    $this->post(route('login.store'), ['login' => $intern->student_id, 'password' => 'SANTIAGO'])
        ->assertRedirect(route('intern.dashboard'));
});

test('sync command never touches interns who picked their own password', function () {
    $intern = makeIntern(['last_name' => 'Bautista', 'password' => 'MyOwn123', 'password_changed_at' => now()]);

    // Even with a name correction, a self-chosen password is the intern's own.
    $intern->update(['last_name' => 'Bautista-Cruz']);

    $this->artisan('ojt:sync-intern-passwords')
        ->expectsOutputToContain('nothing to do')
        ->assertSuccessful();

    expect(Hash::check('MyOwn123', $intern->fresh()->password))->toBeTrue();
});

test('sync command dry run reports without changing anything', function () {
    $intern = makeIntern(['last_name' => 'Mercado']);
    $intern->update(['password' => 'Mercado', 'password_changed_at' => null]);
    $intern->update(['last_name' => 'Mercado-Reyes']);

    $this->artisan('ojt:sync-intern-passwords', ['--dry-run' => true])
        ->expectsOutputToContain('dry run')
        ->assertSuccessful();

    expect(Hash::check('Mercado-Reyes', $intern->fresh()->password))->toBeFalse();
});

test('sync command --all returns self-chosen passwords to the last-name default', function () {
    $chosen = makeIntern(['student_id' => 'T-7001', 'last_name' => 'Villanueva', 'password' => 'Chosen2026', 'password_changed_at' => now()]);
    // Already on the last-name default — the command must leave them alone.
    $untouched = makeIntern(['student_id' => 'T-7002', 'last_name' => 'Aquino']);
    $untouched->update(['password' => 'Aquino']);

    $this->artisan('ojt:sync-intern-passwords', ['--all' => true])
        ->expectsOutputToContain($chosen->student_id)
        ->expectsOutputToContain('1 intern(s) reset')
        ->assertSuccessful();

    $chosen->refresh();

    expect(Hash::check('Villanueva', $chosen->password))->toBeTrue()
        ->and(Hash::check('Chosen2026', $chosen->password))->toBeFalse()
        // Back on the default, so the "change your password" nudge returns.
        ->and($chosen->password_changed_at)->toBeNull()
        // An intern whose hash already matches their last name is skipped by
        // --all, whatever their password_changed_at says — nothing to reset.
        ->and(Hash::check('Aquino', $untouched->fresh()->password))->toBeTrue()
        ->and($untouched->fresh()->password_changed_at)->not->toBeNull();

    $this->post(route('login.store'), ['login' => $chosen->student_id, 'password' => 'VILLANUEVA'])
        ->assertRedirect(route('intern.dashboard'));
});

test('sync command --all dry run reports self-chosen passwords without changing them', function () {
    $chosen = makeIntern(['last_name' => 'Navarro', 'password' => 'Chosen2026', 'password_changed_at' => now()]);

    $this->artisan('ojt:sync-intern-passwords', ['--all' => true, '--dry-run' => true])
        ->expectsOutputToContain('dry run')
        ->assertSuccessful();

    expect(Hash::check('Chosen2026', $chosen->fresh()->password))->toBeTrue()
        ->and($chosen->fresh()->password_changed_at)->not->toBeNull();
});
