<?php

use App\Models\Office;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

uses(RefreshDatabase::class);

/*
 * The Profile page — photo upload for easy recognition, contact info,
 * and the password form. Every role gets one.
 */

beforeEach(function () {
    Storage::fake('public');
});

test('every role can open their profile', function () {
    $intern = makeIntern();
    $admin = makeStaff(['role' => 'admin']);
    $office = Office::create(['name' => 'O', 'type' => 'internal']);
    $supervisor = User::create([
        'role' => 'supervisor',
        'first_name' => 'Super', 'last_name' => 'Visor',
        'email' => 'super@test.dev',
        'password' => 'password',
        'password_changed_at' => now(),
        'office_id' => $office->id,
        'target_hours' => 0,
        'is_active' => true,
        'profile_completed_at' => now(),
    ]);

    $this->actingAs($intern)->get(route('profile'))->assertOk()->assertSee('My Profile');
    $this->actingAs($admin)->get(route('profile'))->assertOk();
    $this->actingAs($supervisor)->get(route('profile'))->assertOk();
});

test('profile requires login', function () {
    $this->get(route('profile'))->assertRedirect(route('login'));
});

test('user can upload a profile photo and it shows in the admin lists', function () {
    $intern = makeIntern();
    $admin = makeStaff(['role' => 'admin']);

    $response = $this->actingAs($intern)->put(route('profile.update'), [
        'email' => $intern->email,
        'avatar' => UploadedFile::fake()->image('me.jpg', 120, 120),
    ]);
    file_put_contents('storage/profile-errors.txt', json_encode(session('errors')));
    $response->assertRedirect(route('profile'));

    $intern->refresh();

    expect($intern->avatar_path)->not->toBeNull();
    Storage::disk('public')->assertExists($intern->avatar_path);

    // The avatar renders on the admin's interns list for recognition.
    $this->actingAs($admin)->get(route('admin.interns.index'))
        ->assertOk()
        ->assertSee($intern->avatar_url);
});

test('re-uploading replaces the old photo and removal clears it', function () {
    $intern = makeIntern();

    $this->actingAs($intern)->put(route('profile.update'), [
        'email' => $intern->email,
        'avatar' => UploadedFile::fake()->image('one.jpg'),
    ]);
    $first = $intern->refresh()->avatar_path;
    Storage::disk('public')->assertExists($first);

    $this->actingAs($intern)->put(route('profile.update'), [
        'email' => $intern->email,
        'avatar' => UploadedFile::fake()->image('two.jpg'),
    ]);
    $second = $intern->refresh()->avatar_path;

    expect($second)->not->toBe($first);
    Storage::disk('public')->assertMissing($first);

    $this->actingAs($intern)->delete(route('profile.avatar.destroy'))
        ->assertRedirect(route('profile'));

    expect($intern->fresh()->avatar_path)->toBeNull();
    Storage::disk('public')->assertMissing($second);
});

test('interns cannot edit their names but staff can', function () {
    $intern = makeIntern();

    $this->actingAs($intern)->put(route('profile.update'), [
        'email' => $intern->email,
        'first_name' => 'Renamed',
        'last_name' => 'McNewface',
    ])->assertSessionHasErrors(['first_name', 'last_name']);

    expect($intern->fresh()->last_name)->toBe('Intern');

    $office = Office::create(['name' => 'O', 'type' => 'internal']);
    $supervisor = User::create([
        'role' => 'supervisor',
        'first_name' => 'Super', 'last_name' => 'Visor',
        'email' => 'super@test.dev',
        'password' => 'password',
        'password_changed_at' => now(),
        'office_id' => $office->id,
        'target_hours' => 0,
        'is_active' => true,
        'profile_completed_at' => now(),
    ]);

    $this->actingAs($supervisor)->put(route('profile.update'), [
        'first_name' => 'Renamed',
        'last_name' => 'McNewface',
        'email' => 'super@test.dev',
    ])->assertRedirect(route('profile'));

    $supervisor->refresh();

    expect($supervisor->first_name)->toBe('Renamed')
        ->and($supervisor->last_name)->toBe('McNewface');
});

test('email must stay unique', function () {
    $other = makeIntern();
    $intern = makeIntern(['student_id' => 'T-1002']);

    $this->actingAs($intern)->put(route('profile.update'), [
        'email' => $other->email,
    ])->assertSessionHasErrors('email');
});
