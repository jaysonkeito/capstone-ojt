<?php

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

/*
 * Flash messages and validation errors surface as auto-dismissing toast
 * notifications (partials/toast) instead of inline banners, so they float
 * over the page and disappear after a few seconds.
 */

function toastStaff(array $attributes = []): User
{
    return User::create([
        'first_name' => 'Toast',
        'last_name' => 'Admin',
        'email' => fake()->unique()->safeEmail(),
        'password' => 'password',
        'role' => 'admin',
        'is_active' => true,
        'password_changed_at' => now(),
        'profile_completed_at' => now(),
        ...$attributes,
    ]);
}

test('success flashes render as auto-dismissing toasts on app pages', function () {
    $admin = toastStaff();

    $this->actingAs($admin)
        ->followingRedirects()
        ->put(route('profile.update'), [
            'email' => $admin->email,
            'first_name' => 'Toast',
            'last_name' => 'Admin',
        ])
        ->assertOk()
        ->assertSee('id="toastStack"', false)
        ->assertSee('data-toast-role="status"', false)
        ->assertSee('Profile updated.');
});

test('validation errors render as error toasts with field-level messages kept on the form', function () {
    $admin = toastStaff();

    // Seed the flashed error bag the way the framework stores it in the
    // session (same technique as InternProfileTest) and fetch a page.
    $this->actingAs($admin)
        ->withSession(['errors' => [
            'default' => [
                'format' => ':message',
                'messages' => ['student_id' => ['The student id field is required.']],
            ],
        ]])
        ->get(route('profile'))
        ->assertOk()
        ->assertSee('data-toast-role="error"', false)
        ->assertSee('The student id field is required.');
});

test('the login page shows the sign-in error as a toast', function () {
    $user = User::create([
        'first_name' => 'Toast',
        'last_name' => 'User',
        'email' => 'wrongpass@test.dev',
        'student_id' => '202300555',
        'password' => 'real-password',
        'role' => 'intern',
        'is_active' => true,
        'password_changed_at' => now(),
        'profile_completed_at' => now(),
    ]);

    $this->from(route('login'))
        ->post(route('login.store'), [
            'login' => '202300555',
            'password' => 'not-the-password',
        ])
        ->assertRedirect(route('login'));

    $this->get(route('login'))
        ->assertOk()
        ->assertSee('data-toast-role="error"', false)
        ->assertSee('These credentials do not match our records.');
});

test('login failures are still detected by the clear-fields guard after the toast markup change', function () {
    $user = User::create([
        'first_name' => 'Toast',
        'last_name' => 'User',
        'email' => 'wrongpass2@test.dev',
        'student_id' => '202300556',
        'password' => 'real-password',
        'role' => 'intern',
        'is_active' => true,
        'password_changed_at' => now(),
        'profile_completed_at' => now(),
    ]);

    $this->from(route('login'))
        ->post(route('login.store'), [
            'login' => '202300556',
            'password' => 'not-the-password',
        ])->assertRedirect(route('login'));

    // The clear-fields script keys off the toast error marker now.
    $html = $this->get(route('login'))->getContent();

    expect($html)->toContain('data-toast-role="error"')
        ->and($html)->toContain("document.querySelector('.toast[data-toast-role=\"error\"]')");
});
