<?php

use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

/*
 * Security hardening: login rate limiting, and the forced first-login
 * password change for interns still on the default (last-name) password.
 */

test('login is throttled after five failed attempts', function () {
    $intern = makeIntern();

    for ($i = 0; $i < 5; $i++) {
        $this->post(route('login.store'), [
            'login' => $intern->student_id,
            'password' => 'wrong-password',
        ]);
    }

    $this->post(route('login.store'), [
        'login' => $intern->student_id,
        'password' => 'password', // even the RIGHT password is locked out
    ]);

    // The flashed error shows on the login page with the countdown.
    $this->get(route('login'))->assertSee('Too many login attempts');
});

test('successful login clears the throttle counter', function () {
    $intern = makeIntern();

    // Four failures, then a successful login — a fifth attempt after that
    // must not be throttled.
    for ($i = 0; $i < 4; $i++) {
        $this->post(route('login.store'), ['login' => $intern->student_id, 'password' => 'nope']);
    }

    $this->post(route('login.store'), ['login' => $intern->student_id, 'password' => 'password'])
        ->assertRedirect();

    $this->post(route('login.store'), ['login' => $intern->student_id, 'password' => 'nope']);
    $this->get(route('login'))->assertDontSee('Too many login attempts');
});

test('intern on the default password reaches the dashboard with a nudge banner', function () {
    $intern = makeIntern(['password_changed_at' => null]);

    $this->actingAs($intern)->get(route('intern.dashboard'))
        ->assertOk()
        ->assertSee('still using the default password')
        ->assertSee('Set your own password');
});

test('intern can set a new password from the profile page', function () {
    $intern = makeIntern(['password_changed_at' => null]);

    $this->actingAs($intern)->put(route('password.update'), [
        'current_password' => 'password',
        'password' => 'dutyday2026',
        'password_confirmation' => 'dutyday2026',
    ])->assertRedirect(route('profile'));

    $intern->refresh();

    expect($intern->password_changed_at)->not->toBeNull()
        ->and(Illuminate\Support\Facades\Hash::check('dutyday2026', $intern->password))->toBeTrue();

    // The nudge banner is gone once the password is their own.
    $this->actingAs($intern)->get(route('intern.dashboard'))
        ->assertOk()
        ->assertDontSee('still using the default password');
});

test('the default last-name password is accepted case-insensitively as the current password', function () {
    $intern = makeIntern([
        'last_name' => 'Francisco',
        'password' => 'Francisco',
        'password_changed_at' => null,
    ]);

    $this->actingAs($intern)->put(route('password.update'), [
        'current_password' => 'francisco', // lowercase
        'password' => 'dutyday2026',
        'password_confirmation' => 'dutyday2026',
    ])->assertRedirect(route('profile'));
});

test('wrong current password and weak new passwords are rejected', function () {
    $intern = makeIntern(['password_changed_at' => null]);

    $this->actingAs($intern)->put(route('password.update'), [
        'current_password' => 'not-my-password',
        'password' => 'dutyday2026',
        'password_confirmation' => 'dutyday2026',
    ])->assertSessionHasErrors('current_password');

    $this->actingAs($intern)->put(route('password.update'), [
        'current_password' => 'password',
        'password' => 'short',
        'password_confirmation' => 'short',
    ])->assertSessionHasErrors('password');

    expect($intern->fresh()->password_changed_at)->toBeNull();
});

test('new password must differ from the current one', function () {
    $intern = makeIntern(['password' => 'Oldpass123', 'password_changed_at' => null]);

    $this->actingAs($intern)->put(route('password.update'), [
        'current_password' => 'Oldpass123',
        'password' => 'Oldpass123',
        'password_confirmation' => 'Oldpass123',
    ])->assertSessionHasErrors('password');
});
