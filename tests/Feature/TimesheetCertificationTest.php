<?php

use App\Models\TimesheetCertification;
use App\Models\User;
use App\Notifications\TimesheetCertified;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

/*
 * Timesheet certification — the office supervisor's formal per-period
 * (calendar month) sign-off on an intern's duty hours. One record per
 * intern per period; the intern is notified; the Word timesheet's
 * certified_* merge keys fill from the record. Certification attests to
 * hours — it never changes them.
 *
 * Reuses the global helpers makeIntern / makeCoordinator / makeSupervisor
 * / makeOffice defined in the sibling feature tests.
 */

test('supervisor certifies an intern\'s hours for a period', function () {
    $office = makeOffice();
    $supervisor = makeSupervisor($office);
    $intern = makeIntern(['office_id' => $office->id]);

    $this->actingAs($supervisor)
        ->post(route('monitor.interns.certify', $intern), ['month' => now()->format('Y-m')])
        ->assertRedirect(route('monitor.intern', $intern))
        ->assertSessionHasNoErrors();

    $certification = TimesheetCertification::firstOrFail();

    expect($certification->supervisor_id)->toBe($supervisor->id)
        ->and($certification->intern_id)->toBe($intern->id)
        ->and($certification->period_start->format('Y-m'))->toBe(now()->format('Y-m'))
        ->and($certification->period_end->isSameDay(now()->endOfMonth()))->toBeTrue()
        // The intern learns their hours were signed off.
        ->and($intern->notifications()->where('type', TimesheetCertified::class)->exists())->toBeTrue();
});

test('certifying the same period twice keeps one record and notifies once', function () {
    $office = makeOffice();
    $supervisor = makeSupervisor($office);
    $intern = makeIntern(['office_id' => $office->id]);

    $this->actingAs($supervisor)->post(route('monitor.interns.certify', $intern), ['month' => now()->format('Y-m')]);
    $this->actingAs($supervisor)->post(route('monitor.interns.certify', $intern), ['month' => now()->format('Y-m')]);

    expect(TimesheetCertification::count())->toBe(1)
        ->and($intern->notifications()->where('type', TimesheetCertified::class)->count())->toBe(1);
});

test('a past month can be certified explicitly', function () {
    $office = makeOffice();
    $supervisor = makeSupervisor($office);
    $intern = makeIntern(['office_id' => $office->id]);
    $lastMonth = now()->subMonth()->format('Y-m');

    $this->actingAs($supervisor)
        ->post(route('monitor.interns.certify', $intern), ['month' => $lastMonth])
        ->assertSessionHasNoErrors();

    expect(TimesheetCertification::firstOrFail()->period_start->format('Y-m'))->toBe($lastMonth);
});

test('a future month cannot be certified', function () {
    $office = makeOffice();
    $supervisor = makeSupervisor($office);
    $intern = makeIntern(['office_id' => $office->id]);

    $this->actingAs($supervisor)
        ->post(route('monitor.interns.certify', $intern), ['month' => now()->addMonth()->format('Y-m')])
        ->assertSessionHasErrors('month');

    expect(TimesheetCertification::count())->toBe(0);
});

test('supervisor cannot certify an intern at another office and coordinator cannot certify', function () {
    $supervisor = makeSupervisor(makeOffice());
    $coordinator = makeCoordinator();
    $intern = makeIntern(['coordinator_id' => $coordinator->id]);

    $this->actingAs($supervisor)
        ->post(route('monitor.interns.certify', $intern), ['month' => now()->format('Y-m')])
        ->assertForbidden();

    // Certification is the office supervisor's duty — the route is
    // supervisor-only and the policy refuses everyone else.
    $this->actingAs($coordinator)
        ->post(route('monitor.interns.certify', $intern), ['month' => now()->format('Y-m')])
        ->assertForbidden();

    expect(TimesheetCertification::count())->toBe(0);
});

test('the monitor intern page lists certifications', function () {
    $office = makeOffice();
    $supervisor = makeSupervisor($office);
    $intern = makeIntern(['office_id' => $office->id]);

    $this->actingAs($supervisor)->post(route('monitor.interns.certify', $intern), ['month' => now()->format('Y-m')]);

    $this->actingAs($supervisor)->get(route('monitor.intern', $intern))
        ->assertOk()
        ->assertSee('Time Frame Certifications')
        ->assertSee(now()->format('F Y'));
});

test('certification survives supervisor deletion as an anonymous sign-off', function () {
    $office = makeOffice();
    $supervisor = makeSupervisor($office);
    $intern = makeIntern(['office_id' => $office->id]);

    $this->actingAs($supervisor)->post(route('monitor.interns.certify', $intern), ['month' => now()->format('Y-m')]);

    // A permanent delete (archive + force-delete) fires the FK's
    // nullOnDelete: the period record survives, only the name is gone.
    // A soft delete alone keeps the FK pointing at the archived account.
    $supervisor->forceDelete();

    expect(TimesheetCertification::firstOrFail()->supervisor_id)->toBeNull()
        ->and(User::find($intern->id) ? true : false)->toBeTrue();
});
