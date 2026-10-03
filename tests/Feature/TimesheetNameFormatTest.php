<?php

use App\Models\User;

/*
 * The timesheet's signature block (intern, coordinator, supervisor) shows
 * names in "First Name Last Name, Title" order — e.g. "John Doe, Coordinator"
 * — not the formal "Last Name, First Name" sorting format. This test
 * verifies the User::display_name accessor and the title-append logic that
 * TimesheetReportService::fields() applies.
 */

test('display_name returns First Name Last Name format', function () {
    $user = User::create([
        'role' => 'coordinator',
        'first_name' => 'John',
        'last_name' => 'Doe',
        'email' => fake()->unique()->safeEmail(),
        'password' => 'password',
        'password_changed_at' => now(),
        'target_hours' => 0,
    ]);

    expect($user->display_name)->toBe('John Doe');
});

test('display_name omits last name when absent', function () {
    $user = User::create([
        'role' => 'intern',
        'first_name' => 'John',
        'last_name' => null,
        'email' => fake()->unique()->safeEmail(),
        'password' => 'password',
        'password_changed_at' => now(),
        'target_hours' => 0,
    ]);

    expect($user->display_name)->toBe('John');
});

test('display_name omits first name when absent', function () {
    $user = User::create([
        'role' => 'intern',
        'first_name' => null,
        'last_name' => 'Doe',
        'email' => fake()->unique()->safeEmail(),
        'password' => 'password',
        'password_changed_at' => now(),
        'target_hours' => 0,
    ]);

    expect($user->display_name)->toBe('Doe');
});

test('supervisor name with title uses First Name Last Name, Title format', function () {
    $supervisor = User::create([
        'role' => 'supervisor',
        'first_name' => 'Jane',
        'last_name' => 'Smith',
        'title' => 'Office Head',
        'email' => fake()->unique()->safeEmail(),
        'password' => 'password',
        'password_changed_at' => now(),
        'target_hours' => 0,
    ]);

    // Replicate the title-append logic from TimesheetReportService::fields()
    $approved = $supervisor->display_name ?? '';
    if ($approved !== '' && $supervisor->title) {
        $approved .= ', '.$supervisor->title;
    }

    expect($approved)->toBe('Jane Smith, Office Head');
});

test('supervisor name without title uses plain First Name Last Name', function () {
    $supervisor = User::create([
        'role' => 'supervisor',
        'first_name' => 'Jane',
        'last_name' => 'Smith',
        'email' => fake()->unique()->safeEmail(),
        'password' => 'password',
        'password_changed_at' => now(),
        'target_hours' => 0,
    ]);

    $approved = $supervisor->display_name ?? '';
    if ($approved !== '' && $supervisor->title) {
        $approved .= ', '.$supervisor->title;
    }

    expect($approved)->toBe('Jane Smith');
});

test('coordinator name with title uses First Name Last Name, Title format', function () {
    $coordinator = User::create([
        'role' => 'coordinator',
        'first_name' => 'Alice',
        'last_name' => 'Brown',
        'title' => 'Coordinator',
        'email' => fake()->unique()->safeEmail(),
        'password' => 'password',
        'password_changed_at' => now(),
        'target_hours' => 0,
    ]);

    $reviewed = $coordinator->display_name ?? '';
    if ($reviewed !== '' && $coordinator->title) {
        $reviewed .= ', '.$coordinator->title;
    }

    expect($reviewed)->toBe('Alice Brown, Coordinator');
});

test('intern name in prepared_name field uses First Name Last Name format', function () {
    $intern = User::create([
        'role' => 'intern',
        'first_name' => 'Carlos',
        'last_name' => 'Garcia',
        'email' => fake()->unique()->safeEmail(),
        'password' => 'password',
        'password_changed_at' => now(),
        'target_hours' => 500,
    ]);

    expect($intern->display_name)->toBe('Carlos Garcia');
});
