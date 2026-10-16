<?php

use App\Models\Office;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;

uses(RefreshDatabase::class);

function completedIntern(array $attributes = []): \App\Models\User
{
    $intern = makeIntern(['target_hours' => 8, ...$attributes]);
    makeLog($intern, makeActiveEnrollment($intern), null, fullDutyTimes());

    return $intern;
}

it('shows the Completed OJT card to the System Admin with finished interns', function () {
    $admin = makeStaff(['role' => 'admin']);
    $finished = completedIntern(['last_name' => 'Finished']);
    makeIntern(['student_id' => 'T-9202', 'last_name' => 'Ongoing']);

    $this->actingAs($admin)->get(route('admin.dashboard'))
        ->assertOk()
        ->assertSee('Completed OJT')
        ->assertSee('Finished');
});

it('scopes the Completed card to the supervisor\'s own office', function () {
    $office = Office::create(['name' => 'Scoped Office', 'type' => 'internal']);
    $supervisor = makeStaff(['role' => 'supervisor', 'office_id' => $office->id, 'profile_completed_at' => now()]);
    $mine = completedIntern(['student_id' => 'T-9203', 'office_id' => $office->id, 'last_name' => 'Mine']);
    completedIntern(['student_id' => 'T-9204', 'last_name' => 'Theirs']);

    $this->actingAs($supervisor)->get(route('admin.dashboard'))
        ->assertOk()
        ->assertSee('Completed OJT')
        ->assertSee('Mine')
        ->assertDontSee('Theirs');
});

it('shows the Completed card on the monitor dashboard for coordinators', function () {
    $coordinator = makeStaff(['role' => 'coordinator', 'profile_completed_at' => now()]);
    completedIntern(['student_id' => 'T-9205', 'coordinator_id' => $coordinator->id, 'last_name' => 'Monitored']);

    $this->actingAs($coordinator)->get(route('monitor.dashboard'))
        ->assertOk()
        ->assertSee('Completed OJT')
        ->assertSee('Monitored');
});

it('carries the notification\'s full content behind a click-to-view row', function () {
    $admin = makeStaff(['role' => 'admin']);

    DB::table('notifications')->insert([
        'id' => Str::uuid()->toString(),
        'type' => 'App\\Notifications\\LogReviewed',
        'notifiable_type' => 'App\\Models\\User',
        'notifiable_id' => $admin->id,
        'data' => json_encode([
            'kind' => 'log_reviewed',
            'decision' => 'approved',
            'reviewer' => 'Juan Dela Cruz',
            'date' => today()->toDateString(),
            'comment' => 'Complete duty day, hours match the schedule.',
        ]),
        'read_at' => null,
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    $this->actingAs($admin)->get(route('notifications.index'))
        ->assertOk()
        ->assertSee('click to view full content')
        ->assertSee('Full content')
        ->assertSee('Reviewed by')
        ->assertSee('Complete duty day, hours match the schedule.');
});
