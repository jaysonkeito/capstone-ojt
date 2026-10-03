<?php

use App\Models\OjtLog;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

/*
 * The admin Logbook — manual create (store) and edit (update) of an intern's
 * daily time record, plus the ordering of the per-day list.
 *
 * Two behaviours are pinned here:
 *   1. A partial entry is allowed. Day-to-day time in/out happens by kiosk
 *      scan, one slot at a time, so the manual form must accept a lone time
 *      (e.g. AM In with no AM Out yet) instead of forcing a whole AM/PM pair.
 *      A wholly blank entry is still refused, and when BOTH ends of a session
 *      are given the Out must come after the In.
 *   2. The list is alphabetical by the intern's name, no matter who scanned or
 *      was entered first.
 *
 * Uses the global helpers makeIntern / makeStaff / makeActiveEnrollment /
 * makeLog defined in the sibling feature tests.
 */

test('a manual entry with only AM In saves — no forced AM/PM pair', function () {
    $admin = makeStaff(['role' => 'admin']);
    $intern = makeIntern();
    makeActiveEnrollment($intern);

    $this->actingAs($admin)
        ->post(route('admin.logs.store'), [
            'user_id' => $intern->id,
            'date' => today()->toDateString(),
            'am_time_in' => '08:00',
        ])
        ->assertRedirect()
        ->assertSessionHasNoErrors();

    $log = OjtLog::where('user_id', $intern->id)->firstOrFail();

    expect(substr($log->am_time_in, 0, 5))->toBe('08:00')
        ->and($log->am_time_out)->toBeNull()
        ->and($log->pm_time_in)->toBeNull()
        ->and($log->pm_time_out)->toBeNull()
        // A half-open session computes no hours yet, without erroring.
        ->and((float) $log->hours_rendered)->toBe(0.0);
});

test('an edit can leave a session half-open (single time)', function () {
    $admin = makeStaff(['role' => 'admin']);
    $intern = makeIntern();
    $enrollment = makeActiveEnrollment($intern);
    $log = makeLog($intern, $enrollment); // starts with no times

    $this->actingAs($admin)
        ->put(route('admin.logs.update', $log), ['am_time_in' => '08:15'])
        ->assertRedirect()
        ->assertSessionHasNoErrors();

    $log->refresh();

    expect(substr($log->am_time_in, 0, 5))->toBe('08:15')
        ->and($log->am_time_out)->toBeNull();
});

test('a wholly blank manual entry is refused', function () {
    $admin = makeStaff(['role' => 'admin']);
    $intern = makeIntern();
    makeActiveEnrollment($intern);

    // No times at all — nothing to record.
    $this->actingAs($admin)
        ->post(route('admin.logs.store'), [
            'user_id' => $intern->id,
            'date' => today()->toDateString(),
        ])
        ->assertSessionHasErrors('am_time_in');

    expect(OjtLog::count())->toBe(0);
});

test('clearing every time on an edit is refused', function () {
    $admin = makeStaff(['role' => 'admin']);
    $intern = makeIntern();
    $enrollment = makeActiveEnrollment($intern);
    $log = makeLog($intern, $enrollment, times: ['am_time_in' => '08:00']);

    $this->actingAs($admin)
        ->put(route('admin.logs.update', $log), [
            'am_time_in' => '',
            'am_time_out' => '',
            'pm_time_in' => '',
            'pm_time_out' => '',
        ])
        ->assertSessionHasErrors('am_time_in');

    // The existing time is left untouched.
    expect(substr($log->refresh()->am_time_in, 0, 5))->toBe('08:00');
});

test('when both ends of a session are given, Out must come after In', function () {
    $admin = makeStaff(['role' => 'admin']);
    $intern = makeIntern();
    $enrollment = makeActiveEnrollment($intern);

    // AM Out before AM In (store path).
    $this->actingAs($admin)
        ->post(route('admin.logs.store'), [
            'user_id' => $intern->id,
            'date' => today()->toDateString(),
            'am_time_in' => '10:00',
            'am_time_out' => '09:00',
        ])
        ->assertSessionHasErrors('am_time_out');

    expect(OjtLog::count())->toBe(0);

    // PM Out before PM In (edit path).
    $log = makeLog($intern, $enrollment);

    $this->actingAs($admin)
        ->put(route('admin.logs.update', $log), [
            'pm_time_in' => '15:00',
            'pm_time_out' => '14:00',
        ])
        ->assertSessionHasErrors('pm_time_out');

    expect($log->refresh()->pm_time_out)->toBeNull();
});

/*
 * The New Entry picker — interns who already have an entry for the viewed
 * date (by scan or by hand) no longer appear in it, since one day = one
 * entry per intern (the date-unique rule in validateLog).
 */

test('the New Entry picker drops interns who already logged the viewed date', function () {
    $admin = makeStaff(['role' => 'admin']);

    $logged = makeIntern(['first_name' => 'Ida', 'last_name' => 'Logged', 'student_id' => 'S-11']);
    $waiting = makeIntern(['first_name' => 'Wes', 'last_name' => 'Waiting', 'student_id' => 'S-12']);

    makeLog($logged, makeActiveEnrollment($logged), times: ['am_time_in' => '08:00']);

    $response = $this->actingAs($admin)
        ->get(route('admin.logs.index'))
        ->assertOk();

    // The intern already on the list (their row) is gone from the picker;
    // the one still unlogged for this date remains searchable.
    $content = $response->getContent();
    $picker = substr($content, (int) strpos($content, 'name="user_id"'));
    $picker = substr($picker, 0, (int) strpos($picker, '</select>'));

    expect($picker)->not->toContain(e($logged->full_name.' ('.$logged->student_id.')'))
        ->and($picker)->toContain(e($waiting->full_name.' ('.$waiting->student_id.')'));
});

test('a stepped-out gap pair can be entered by hand and the away time is not counted', function () {
    $admin = makeStaff(['role' => 'admin']);
    $intern = makeIntern();
    makeActiveEnrollment($intern);

    $this->actingAs($admin)
        ->post(route('admin.logs.store'), [
            'user_id' => $intern->id,
            'date' => today()->toDateString(),
            'am_time_in' => '08:00',
            'am_time_out' => '10:30',
            'am_time_in_2' => '11:00',
            'am_time_out_2' => '12:00',
            'pm_time_in' => '13:00',
            'pm_time_out' => '17:00',
        ])
        ->assertSessionHasNoErrors();

    $log = OjtLog::where('user_id', $intern->id)->whereDate('date', today())->firstOrFail();

    // 8:00–10:30 + 11:00–12:00 + 13:00–17:00 — the 10:30–11:00 step away is
    // excluded, so the day reads 7.5h instead of the naive 8h.
    expect((float) $log->regular_hours)->toBe(7.5)
        ->and((float) $log->hours_rendered)->toBe(7.5);
});

test('gap times must follow the session times they extend', function () {
    $admin = makeStaff(['role' => 'admin']);
    $intern = makeIntern();
    $enrollment = makeActiveEnrollment($intern);

    $this->actingAs($admin);

    // AM In (2) at or before the early-out AM Out makes no sense.
    $this->post(route('admin.logs.store'), [
        'user_id' => $intern->id,
        'date' => today()->toDateString(),
        'am_time_in' => '08:00',
        'am_time_out' => '10:30',
        'am_time_in_2' => '10:00',
    ])->assertSessionHasErrors('am_time_in_2');

    // Same for the PM session (edit path).
    $log = makeLog($intern, $enrollment, times: [
        'am_time_in' => '08:00',
        'am_time_out' => '12:00',
        'pm_time_in' => '13:00',
        'pm_time_out' => '15:00',
    ]);

    $this->put(route('admin.logs.update', $log), [
        'pm_time_out' => '15:00',
        'pm_time_in_2' => '14:30',
    ])->assertSessionHasErrors('pm_time_in_2');

    expect($log->refresh()->pm_time_in_2)->toBeNull();
});

test('the day list is alphabetical by name, not by who logged first', function () {
    $admin = makeStaff(['role' => 'admin']);

    // Created in a deliberately non-alphabetical order so a created_at sort
    // (the old behaviour) would put Zamora first.
    $zamora = makeIntern(['first_name' => 'Zed', 'last_name' => 'Zamora', 'student_id' => 'S-01']);
    $alvarez = makeIntern(['first_name' => 'Ana', 'last_name' => 'Alvarez', 'student_id' => 'S-02']);
    $mendoza = makeIntern(['first_name' => 'Mia', 'last_name' => 'Mendoza', 'student_id' => 'S-03']);

    makeLog($zamora, makeActiveEnrollment($zamora), times: ['am_time_in' => '08:00']);
    makeLog($alvarez, makeActiveEnrollment($alvarez), times: ['am_time_in' => '08:05']);
    makeLog($mendoza, makeActiveEnrollment($mendoza), times: ['am_time_in' => '08:10']);

    // Assert on the per-intern show links, which appear only in the table rows
    // (not in the New Entry modal's select), so this pins the table's order.
    $this->actingAs($admin)
        ->get(route('admin.logs.index'))
        ->assertOk()
        ->assertSeeInOrder([
            route('admin.logs.show', $alvarez),
            route('admin.logs.show', $mendoza),
            route('admin.logs.show', $zamora),
        ]);
});
