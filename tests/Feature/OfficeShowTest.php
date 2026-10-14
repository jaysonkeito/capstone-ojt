<?php

use App\Models\Office;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('shows an office with its details and placed interns to the System Admin', function () {
    $admin = makeStaff(['role' => 'admin']);
    $office = Office::create(['name' => 'NORSU-BSC MIS Office', 'type' => 'internal', 'city' => 'Bayawan City']);
    $intern = makeIntern(['office_id' => $office->id, 'last_name' => 'Sumalpong']);
    makeLog($intern, makeActiveEnrollment($intern), null, fullDutyTimes());

    $this->actingAs($admin)->get(route('admin.offices.show', $office))
        ->assertOk()
        ->assertSee('← Offices')
        ->assertSee($office->name)
        ->assertSee('Bayawan City')
        ->assertSee('Sumalpong')
        ->assertSee('8.0');
});

it('shows the office page to a coordinator', function () {
    $coordinator = makeStaff(['role' => 'coordinator', 'profile_completed_at' => now()]);
    $office = Office::create(['name' => 'CSIT Faculty Office', 'type' => 'internal']);

    $this->actingAs($coordinator)->get(route('admin.offices.show', $office))->assertOk();
});

it('denies the office page to supervisors and interns', function () {
    $office = Office::create(['name' => 'External Office', 'type' => 'external']);
    $supervisor = makeStaff(['role' => 'supervisor', 'office_id' => $office->id, 'profile_completed_at' => now()]);
    $intern = makeIntern();

    $this->actingAs($supervisor)->get(route('admin.offices.show', $office))->assertForbidden();
    $this->actingAs($intern)->get(route('admin.offices.show', $office))->assertForbidden();
});

it('marks office working-time overrides and campus defaults on the page', function () {
    $admin = makeStaff(['role' => 'admin']);
    $office = Office::create([
        'name' => 'Early Office',
        'type' => 'internal',
        'am_time_in' => '07:30',
    ]);

    $this->actingAs($admin)->get(route('admin.offices.show', $office))
        ->assertOk()
        ->assertSee('Office override')
        ->assertSee('Campus default')
        ->assertSee('7:30 AM');
});

it('links office names on the index to the detail page', function () {
    $admin = makeStaff(['role' => 'admin']);
    $office = Office::create(['name' => 'Linked Office', 'type' => 'internal']);

    $this->actingAs($admin)->get(route('admin.offices.index'))
        ->assertOk()
        ->assertSee(route('admin.offices.show', $office));
});
