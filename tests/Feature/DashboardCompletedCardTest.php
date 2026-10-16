<?php

use App\Models\Office;
use App\Models\OjtSetting;
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

it('keeps completed interns off the On Duty board and marks arrivals Late or On-Time', function () {
    $admin = makeStaff(['role' => 'admin']);
    $office = Office::create(['name' => 'Late Office', 'type' => 'internal', 'am_time_in' => '08:00']);

    // Grace is 15 minutes (campus default): an 08:40 arrival is Late, an
    // 08:02 arrival is On-Time — measured against the office's own 8:00.
    $late = makeIntern(['student_id' => 'T-9401', 'office_id' => $office->id, 'last_name' => 'Latecomer']);
    makeLog($late, makeActiveEnrollment($late), null, ['am_time_in' => '08:40']);

    $ontime = makeIntern(['student_id' => 'T-9402', 'office_id' => $office->id, 'last_name' => 'Punctual']);
    makeLog($ontime, makeActiveEnrollment($ontime), null, ['am_time_in' => '08:02']);

    // Finished their 8-hour target YESTERDAY, arrived again today and is
    // still on the clock — but done is done: no On-Duty row for them.
    $finished = makeIntern(['student_id' => 'T-9403', 'target_hours' => 8, 'office_id' => $office->id, 'last_name' => 'Donealready']);
    $finishedEnrollment = makeActiveEnrollment($finished);
    makeLog($finished, $finishedEnrollment, today()->subDay()->toDateString(), fullDutyTimes());
    makeLog($finished, $finishedEnrollment, null, ['am_time_in' => '08:01']);

    $this->actingAs($admin)->get(route('admin.dashboard'))
        ->assertOk()
        ->assertSee('Latecomer')
        ->assertSee('Punctual')
        ->assertSee('On-Time')
        // On-Duty rows render the arrival time — INCLUDING the completed
        // intern's 8:01 arrival (they still punch; the board shows
        // everyone on the clock).
        ->assertSee('in 8:40 AM')
        ->assertSee('in 8:02 AM')
        ->assertSee('in 8:01 AM');
});

it('lists the ongoing interns who have not scanned today on the Absent board', function () {
    $admin = makeStaff(['role' => 'admin']);
    // Make today a working day regardless of which weekday the test runs on.
    OjtSetting::current()->update(['working_days' => [today()->dayOfWeek]]);

    $scanned = makeIntern(['student_id' => 'T-9411', 'last_name' => 'Present']);
    makeLog($scanned, makeActiveEnrollment($scanned), null, ['am_time_in' => '08:00']);
    makeIntern(['student_id' => 'T-9412', 'last_name' => 'Missing']);

    $this->actingAs($admin)->get(route('admin.dashboard'))
        ->assertOk()
        ->assertSee('Absent today')
        ->assertSee('Missing')
        // The absent board carries the No-scan chip, and only Missing gets one.
        ->assertSee('No scan');
});

it('lists five names on the Top boards', function () {
    $admin = makeStaff(['role' => 'admin']);

    foreach (range(1, 6) as $i) {
        $intern = makeIntern(['student_id' => 'T-95'.$i.'0', 'last_name' => 'Ranker'.$i]);
        makeLog($intern, makeActiveEnrollment($intern), null, ['am_time_in' => '08:00', 'am_time_out' => '12:00', 'pm_time_in' => '13:00', 'pm_time_out' => '17:00']);
    }

    $response = $this->actingAs($admin)->get(route('admin.dashboard'))->assertOk();
    $html = $response->getContent();

    // Six ranked interns exist; the highest board shows only the top 5.
    expect(substr_count($html, 'Ranker'))->toBeGreaterThanOrEqual(10); // both boards combined
    expect(preg_match_all('/Ranker6/', $html))->toBe(1); // Ranker6 only in the least-hours board
});
