<?php

use App\Models\OjtEnrollment;
use App\Models\OjtLog;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

uses(RefreshDatabase::class);

/*
 * Office kiosk — the front-desk scanning station wired to the QR scanner box.
 * An admin is logged in on the kiosk PC; interns present their PERSONAL QR
 * (which carries their own token, unlike the shared wall poster). Each scan
 * posts the decoded code to admin.kiosk.scan, which resolves the intern and
 * advances their day through the same AttendanceRecorder the phone flow uses.
 *
 * Uses the intern/enrollment/log helpers from InternPhotoUploadTest and the
 * staff helper from DailyReportAccessTest.
 */

test('the kiosk station page is admin-only', function () {
    $admin = makeStaff(['role' => 'admin']);
    $intern = makeIntern();

    $this->actingAs($admin)->get(route('admin.kiosk.index'))
        ->assertOk()
        ->assertSee('Ready to scan')
        // The status light reports focus ("Listening for scans"), not the
        // hardware — a keyboard-wedge scanner can't be sensed by the browser,
        // so the old always-on "Scanner active" claim is gone.
        ->assertSee('Listening for scans')
        ->assertDontSee('Scanner active');

    $this->actingAs($intern)->get(route('admin.kiosk.index'))->assertForbidden();
});

test('the scan endpoint is admin-only', function () {
    $intern = makeIntern();
    makeActiveEnrollment($intern);

    $this->actingAs($intern)
        ->postJson(route('admin.kiosk.scan'), ['code' => $intern->scanQrPayload()])
        ->assertForbidden();

    expect(OjtLog::count())->toBe(0);
});

test('the keep-alive ping answers no content and is admin-only', function () {
    $admin = makeStaff(['role' => 'admin']);
    $intern = makeIntern();

    // The station pings this on a timer to keep the admin session from going
    // idle between scans. It carries nothing and answers 204.
    $this->actingAs($admin)->get(route('admin.kiosk.ping'))->assertNoContent();

    // It lives behind the same admin gate as the rest of the station.
    $this->actingAs($intern)->get(route('admin.kiosk.ping'))->assertForbidden();
});

test('a valid scan records the AM time in', function () {
    $admin = makeStaff(['role' => 'admin']);
    $intern = makeIntern();
    makeActiveEnrollment($intern);

    $this->travelTo(now()->setTime(8, 3));

    $this->actingAs($admin)
        ->postJson(route('admin.kiosk.scan'), ['code' => $intern->scanQrPayload()])
        ->assertOk()
        ->assertJson([
            'state' => 'recorded',
            'action' => 'AM Time In',
        ])
        ->assertJsonPath('intern.name', $intern->full_name);

    $log = OjtLog::where('user_id', $intern->id)->whereDate('date', today())->first();

    expect($log)->not->toBeNull()
        ->and(substr($log->am_time_in, 0, 5))->toBe('08:03')
        ->and($log->logged_by)->toBe($intern->id)
        ->and($log->am_time_out)->toBeNull();
});

test('successive scans advance the slots in order', function () {
    $admin = makeStaff(['role' => 'admin']);
    $intern = makeIntern();
    makeActiveEnrollment($intern);
    $payload = $intern->scanQrPayload();

    $this->actingAs($admin);

    $this->travelTo(now()->setTime(8, 0));
    $this->postJson(route('admin.kiosk.scan'), ['code' => $payload])->assertJson(['action' => 'AM Time In']);

    $this->travelTo(now()->setTime(12, 0));
    $this->postJson(route('admin.kiosk.scan'), ['code' => $payload])->assertJson(['action' => 'AM Time Out']);

    $this->travelTo(now()->setTime(13, 0));
    $this->postJson(route('admin.kiosk.scan'), ['code' => $payload])->assertJson(['action' => 'PM Time In']);

    $this->travelTo(now()->setTime(17, 0));
    $this->postJson(route('admin.kiosk.scan'), ['code' => $payload])->assertJson(['action' => 'PM Time Out']);

    $log = OjtLog::where('user_id', $intern->id)->whereDate('date', today())->firstOrFail();

    expect(substr($log->am_time_in, 0, 5))->toBe('08:00')
        ->and(substr($log->am_time_out, 0, 5))->toBe('12:00')
        ->and(substr($log->pm_time_in, 0, 5))->toBe('13:00')
        ->and(substr($log->pm_time_out, 0, 5))->toBe('17:00')
        ->and((float) $log->hours_rendered)->toBe(8.0);

    // A fifth scan has nothing left to fill.
    $this->travelTo(now()->setTime(18, 0));
    $this->postJson(route('admin.kiosk.scan'), ['code' => $payload])->assertJson(['state' => 'done']);
});

/*
 * Stepped out and came back — the intern early-outs (scan before the next
 * boundary closes the session's main pair), and their NEXT scan decides by
 * the clock: before the boundary it steps back into the same session (the
 * "(2)" gap pair); at or after it, the intern is on lunch / gone and the
 * spine continues normally. The away time between the early out and the
 * return is never counted, but everything worked before and after is.
 */

test('an early out and a return before the PM window fills the gap pair instead of hijacking PM', function () {
    $admin = makeStaff(['role' => 'admin']);
    $intern = makeIntern();
    makeActiveEnrollment($intern);
    $payload = $intern->scanQrPayload();

    $this->actingAs($admin);

    $this->travelTo(now()->setTime(8, 0));
    $this->postJson(route('admin.kiosk.scan'), ['code' => $payload])->assertJson(['action' => 'AM Time In']);

    // Early out mid-morning — closes the main pair like any other scan.
    $this->travelTo(now()->setTime(10, 30));
    $this->postJson(route('admin.kiosk.scan'), ['code' => $payload])
        ->assertJson(['state' => 'recorded', 'action' => 'AM Time Out']);

    // Back at 11:00 — still morning, so this is a STEP BACK IN (AM In (2)),
    // not "PM Time In at 11:00" the way the old sequential advance labeled it.
    $this->travelTo(now()->setTime(11, 0));
    $this->postJson(route('admin.kiosk.scan'), ['code' => $payload])
        ->assertJson(['state' => 'recorded', 'action' => 'AM Time In (2)']);

    // Leaving again before the window ends closes the gap pair.
    $this->travelTo(now()->setTime(11, 45));
    $this->postJson(route('admin.kiosk.scan'), ['code' => $payload])
        ->assertJson(['state' => 'recorded', 'action' => 'AM Time Out (2)']);

    // Lunch and the afternoon proceed untouched.
    $this->travelTo(now()->setTime(13, 0));
    $this->postJson(route('admin.kiosk.scan'), ['code' => $payload])->assertJson(['action' => 'PM Time In']);

    $this->travelTo(now()->setTime(17, 0));
    $this->postJson(route('admin.kiosk.scan'), ['code' => $payload])->assertJson(['action' => 'PM Time Out']);

    $log = OjtLog::where('user_id', $intern->id)->whereDate('date', today())->firstOrFail();

    expect(substr($log->am_time_in, 0, 5))->toBe('08:00')
        ->and(substr($log->am_time_out, 0, 5))->toBe('10:30')
        ->and(substr($log->am_time_in_2, 0, 5))->toBe('11:00')
        ->and(substr($log->am_time_out_2, 0, 5))->toBe('11:45')
        ->and(substr($log->pm_time_in, 0, 5))->toBe('13:00')
        ->and(substr($log->pm_time_out, 0, 5))->toBe('17:00')
        // Worked 8:00–10:30 + 11:00–11:45 + 13:00–17:00; the 30 minutes away
        // is simply not counted (2.5 + 0.75 + 4), all of it inside the
        // standard windows.
        ->and((float) $log->regular_hours)->toBe(7.25)
        ->and($log->overtime_hours)->toBe('0.00')
        ->and((float) $log->hours_rendered)->toBe(7.25);
});

test('a return at or after the PM window opens the afternoon and leaves the gap pair empty', function () {
    $admin = makeStaff(['role' => 'admin']);
    $intern = makeIntern();
    makeActiveEnrollment($intern);
    $payload = $intern->scanQrPayload();

    $this->actingAs($admin);

    $this->travelTo(now()->setTime(8, 0));
    $this->postJson(route('admin.kiosk.scan'), ['code' => $payload])->assertJson(['action' => 'AM Time In']);

    $this->travelTo(now()->setTime(10, 30));
    $this->postJson(route('admin.kiosk.scan'), ['code' => $payload])->assertJson(['action' => 'AM Time Out']);

    // Back at 1:10 PM — they went straight to lunch; the scan opens the
    // afternoon instead of writing a morning time into AM In (2). The
    // afternoon has formally started, so no lunch-window note is needed.
    $this->travelTo(now()->setTime(13, 10));
    $this->postJson(route('admin.kiosk.scan'), ['code' => $payload])
        ->assertJson(['state' => 'recorded', 'action' => 'PM Time In', 'note' => null]);

    $this->travelTo(now()->setTime(17, 10));
    $this->postJson(route('admin.kiosk.scan'), ['code' => $payload])->assertJson(['action' => 'PM Time Out']);

    $log = OjtLog::where('user_id', $intern->id)->whereDate('date', today())->firstOrFail();

    expect($log->am_time_in_2)->toBeNull()
        ->and($log->am_time_out_2)->toBeNull()
        ->and(substr($log->pm_time_in, 0, 5))->toBe('13:10')
        // 2.5h morning + 3h50m afternoon in-window + 10m past PM Out as OT.
        ->and((float) $log->regular_hours)->toBe(6.33)
        ->and((float) $log->hours_rendered)->toBe(6.5);
});

test('a second mid-morning excursion after the gap pair closed is refused, not mislabeled', function () {
    $admin = makeStaff(['role' => 'admin']);
    $intern = makeIntern();
    makeActiveEnrollment($intern);
    $payload = $intern->scanQrPayload();

    $this->actingAs($admin);

    $this->travelTo(now()->setTime(8, 0));
    $this->postJson(route('admin.kiosk.scan'), ['code' => $payload])->assertJson(['action' => 'AM Time In']);

    $this->travelTo(now()->setTime(10, 30));
    $this->postJson(route('admin.kiosk.scan'), ['code' => $payload])->assertJson(['action' => 'AM Time Out']);

    $this->travelTo(now()->setTime(11, 0));
    $this->postJson(route('admin.kiosk.scan'), ['code' => $payload])->assertJson(['action' => 'AM Time In (2)']);

    $this->travelTo(now()->setTime(11, 45));
    $this->postJson(route('admin.kiosk.scan'), ['code' => $payload])->assertJson(['action' => 'AM Time Out (2)']);

    // One gap pair per session: a scan at 11:56 — a third mid-morning event
    // before the lunch window opens (which the schema can't hold) — gets a
    // soft "done" instead of writing a morning time into PM In.
    $this->travelTo(now()->setTime(11, 56));
    $this->postJson(route('admin.kiosk.scan'), ['code' => $payload])
        ->assertJson(['state' => 'done']);

    // The day still works normally once the afternoon opens.
    $this->travelTo(now()->setTime(13, 0));
    $this->postJson(route('admin.kiosk.scan'), ['code' => $payload])->assertJson(['action' => 'PM Time In']);

    $log = OjtLog::where('user_id', $intern->id)->whereDate('date', today())->firstOrFail();

    expect($log->am_time_out_2)->not->toBeNull()
        ->and($log->pm_time_in)->not->toBeNull()
        ->and($log->pm_time_out)->toBeNull();
});

test('an early out and a time in during the lunch window counts as PM Time In', function () {
    $admin = makeStaff(['role' => 'admin']);
    $intern = makeIntern();
    makeActiveEnrollment($intern);
    $payload = $intern->scanQrPayload();

    $this->actingAs($admin);

    $this->travelTo(now()->setTime(8, 0));
    $this->postJson(route('admin.kiosk.scan'), ['code' => $payload])->assertJson(['action' => 'AM Time In']);

    // Early out mid-morning closes the morning's main pair.
    $this->travelTo(now()->setTime(10, 30));
    $this->postJson(route('admin.kiosk.scan'), ['code' => $payload])->assertJson(['action' => 'AM Time Out']);

    // Back at 12:30 — inside the lunch window (noon to PM start). This is
    // already the afternoon, not a step back into the morning: the scan
    // opens PM Time In and leaves the morning gap pair dangling.
    $this->travelTo(now()->setTime(12, 30));
    $this->postJson(route('admin.kiosk.scan'), ['code' => $payload])
        ->assertJson(['state' => 'recorded', 'action' => 'PM Time In'])
        // The result card explains the early return so the PM label doesn't
        // look like a mistake to the intern (or the desk operator).
        ->assertJsonPath('note', fn (?string $note) => $note !== null
            && str_contains($note, 'Counted as PM Time In')
            && str_contains($note, '1:00 PM'));

    $this->travelTo(now()->setTime(17, 0));
    $this->postJson(route('admin.kiosk.scan'), ['code' => $payload])->assertJson(['action' => 'PM Time Out']);

    $log = OjtLog::where('user_id', $intern->id)->whereDate('date', today())->firstOrFail();

    expect($log->am_time_in_2)->toBeNull()
        ->and($log->am_time_out_2)->toBeNull()
        ->and(substr($log->am_time_in, 0, 5))->toBe('08:00')
        ->and(substr($log->am_time_out, 0, 5))->toBe('10:30')
        ->and(substr($log->pm_time_in, 0, 5))->toBe('12:30')
        ->and(substr($log->pm_time_out, 0, 5))->toBe('17:00')
        // 2.5h morning + 4h afternoon in-window; the 12:30–13:00 early
        // arrival is the only overtime.
        ->and((float) $log->regular_hours)->toBe(6.5)
        ->and((float) $log->overtime_hours)->toBe(0.5)
        ->and((float) $log->hours_rendered)->toBe(7.0);
});

test('a time in at noon sharp after an early out also counts as PM Time In', function () {
    $admin = makeStaff(['role' => 'admin']);
    $intern = makeIntern();
    makeActiveEnrollment($intern);
    $payload = $intern->scanQrPayload();

    $this->actingAs($admin);

    $this->travelTo(now()->setTime(8, 0));
    $this->postJson(route('admin.kiosk.scan'), ['code' => $payload])->assertJson(['action' => 'AM Time In']);

    $this->travelTo(now()->setTime(11, 0));
    $this->postJson(route('admin.kiosk.scan'), ['code' => $payload])->assertJson(['action' => 'AM Time Out']);

    // Back exactly at noon — the morning session has ended, so the scan
    // opens the afternoon.
    $this->travelTo(now()->setTime(12, 0));
    $this->postJson(route('admin.kiosk.scan'), ['code' => $payload])
        ->assertJson(['state' => 'recorded', 'action' => 'PM Time In'])
        ->assertJsonPath('note', fn (?string $note) => $note !== null
            && str_contains($note, '12:00 PM'));

    $log = OjtLog::where('user_id', $intern->id)->whereDate('date', today())->firstOrFail();

    expect($log->am_time_in_2)->toBeNull()
        ->and(substr($log->pm_time_in, 0, 5))->toBe('12:00');
});

test('a return before noon still steps back into the morning gap pair', function () {
    $admin = makeStaff(['role' => 'admin']);
    $intern = makeIntern();
    makeActiveEnrollment($intern);
    $payload = $intern->scanQrPayload();

    $this->actingAs($admin);

    $this->travelTo(now()->setTime(8, 0));
    $this->postJson(route('admin.kiosk.scan'), ['code' => $payload])->assertJson(['action' => 'AM Time In']);

    $this->travelTo(now()->setTime(10, 30));
    $this->postJson(route('admin.kiosk.scan'), ['code' => $payload])->assertJson(['action' => 'AM Time Out']);

    // Back at 11:55 — one minute before the AM window ends, still morning:
    // this remains a step back in, not an early PM Time In.
    $this->travelTo(now()->setTime(11, 55));
    $this->postJson(route('admin.kiosk.scan'), ['code' => $payload])
        ->assertJson(['state' => 'recorded', 'action' => 'AM Time In (2)']);

    $log = OjtLog::where('user_id', $intern->id)->whereDate('date', today())->firstOrFail();

    expect(substr($log->am_time_in_2, 0, 5))->toBe('11:55')
        ->and($log->pm_time_in)->toBeNull();
});

test('the afternoon supports its own stepped-out episode', function () {
    $admin = makeStaff(['role' => 'admin']);
    $intern = makeIntern();
    makeActiveEnrollment($intern);
    $payload = $intern->scanQrPayload();

    $this->actingAs($admin);

    $this->travelTo(now()->setTime(8, 0));
    $this->postJson(route('admin.kiosk.scan'), ['code' => $payload])->assertJson(['action' => 'AM Time In']);

    $this->travelTo(now()->setTime(12, 0));
    $this->postJson(route('admin.kiosk.scan'), ['code' => $payload])->assertJson(['action' => 'AM Time Out']);

    $this->travelTo(now()->setTime(13, 0));
    $this->postJson(route('admin.kiosk.scan'), ['code' => $payload])->assertJson(['action' => 'PM Time In']);

    // Early out at 4:00, back at 4:30, final departure 5:10.
    $this->travelTo(now()->setTime(16, 0));
    $this->postJson(route('admin.kiosk.scan'), ['code' => $payload])
        ->assertJson(['state' => 'recorded', 'action' => 'PM Time Out']);

    $this->travelTo(now()->setTime(16, 30));
    $this->postJson(route('admin.kiosk.scan'), ['code' => $payload])
        ->assertJson(['state' => 'recorded', 'action' => 'PM Time In (2)']);

    $this->travelTo(now()->setTime(17, 10));
    $this->postJson(route('admin.kiosk.scan'), ['code' => $payload])
        ->assertJson(['state' => 'recorded', 'action' => 'PM Time Out (2)']);

    $log = OjtLog::where('user_id', $intern->id)->whereDate('date', today())->firstOrFail();

    expect(substr($log->pm_time_out, 0, 5))->toBe('16:00')
        ->and(substr($log->pm_time_in_2, 0, 5))->toBe('16:30')
        ->and(substr($log->pm_time_out_2, 0, 5))->toBe('17:10')
        // 4h morning + 3h + 30m back-in-window + 10m past day end as OT.
        ->and((float) $log->regular_hours)->toBe(7.5)
        ->and((float) $log->overtime_hours)->toBe(0.17)
        ->and((float) $log->hours_rendered)->toBe(7.67)
        // The day is genuinely full now — one more scan is done.
        ->and(true)->toBeTrue();

    $this->travelTo(now()->setTime(17, 40));
    $this->postJson(route('admin.kiosk.scan'), ['code' => $payload])->assertJson(['state' => 'done']);
});

test('a scan past the standard day end after the final PM out is done, not a phantom step back in', function () {
    $admin = makeStaff(['role' => 'admin']);
    $intern = makeIntern();
    makeActiveEnrollment($intern);
    $payload = $intern->scanQrPayload();

    $this->actingAs($admin);

    $this->travelTo(now()->setTime(13, 0));
    $this->postJson(route('admin.kiosk.scan'), ['code' => $payload])->assertJson(['action' => 'PM Time In']);

    $this->travelTo(now()->setTime(17, 0));
    $this->postJson(route('admin.kiosk.scan'), ['code' => $payload])->assertJson(['action' => 'PM Time Out']);

    // A stray scan after the day has ended (cooldown elapsed) must not open
    // a PM gap pair and manufacture extra hours.
    $this->travelTo(now()->setTime(17, 15));
    $this->postJson(route('admin.kiosk.scan'), ['code' => $payload])
        ->assertJson(['state' => 'done']);

    $log = OjtLog::where('user_id', $intern->id)->whereDate('date', today())->firstOrFail();

    expect($log->pm_time_in_2)->toBeNull()
        ->and($log->pm_time_out_2)->toBeNull();
});

test('the cooldown still guards a step back in', function () {
    $admin = makeStaff(['role' => 'admin']);
    $intern = makeIntern();
    makeActiveEnrollment($intern);
    $payload = $intern->scanQrPayload();

    $this->actingAs($admin);

    $this->travelTo(now()->setTime(8, 0));
    $this->postJson(route('admin.kiosk.scan'), ['code' => $payload])->assertJson(['action' => 'AM Time In']);

    $this->travelTo(now()->setTime(10, 30));
    $this->postJson(route('admin.kiosk.scan'), ['code' => $payload])->assertJson(['action' => 'AM Time Out']);

    // Back five minutes later — inside the cooldown, refused.
    $this->travelTo(now()->setTime(10, 35));
    $this->postJson(route('admin.kiosk.scan'), ['code' => $payload])
        ->assertJson(['state' => 'too_soon']);

    // And a full minute before the early-out time is out of order.
    $this->travelTo(now()->setTime(10, 45));
    $this->postJson(route('admin.kiosk.scan'), ['code' => $payload])
        ->assertJson(['state' => 'recorded', 'action' => 'AM Time In (2)']);

    $log = OjtLog::where('user_id', $intern->id)->whereDate('date', today())->firstOrFail();

    expect(substr($log->am_time_in_2, 0, 5))->toBe('10:45')
        ->and($log->am_time_out_2)->toBeNull();
});

test('latest_punch reports a step back in as the most recent punch', function () {
    $intern = makeIntern();
    $enrollment = makeActiveEnrollment($intern);

    // 8:00 in, out early at 10:30, back at 11:00 — the gap In (2) is the
    // most recent punch and it is a time IN.
    $log = makeLog($intern, $enrollment, times: [
        'am_time_in' => '08:00',
        'am_time_out' => '10:30',
        'am_time_in_2' => '11:00',
    ]);

    $punch = $log->latest_punch;

    expect($punch['slot'])->toBe('am_time_in_2')
        ->and($punch['label'])->toBe('AM Time In (2)')
        ->and($punch['direction'])->toBe('in')
        ->and($punch['time'])->toBe('11:00 AM');

    // Closing the gap makes Out (2) the latest, a time OUT.
    $log->update(['am_time_out_2' => '11:45']);

    $punch = $log->fresh()->latest_punch;

    expect($punch['slot'])->toBe('am_time_out_2')
        ->and($punch['direction'])->toBe('out');
});

test('an intern dashboard banner after a step back in names the (2) slot', function () {
    $intern = makeIntern();
    $enrollment = makeActiveEnrollment($intern);

    $this->travelTo(today()->setTime(11, 10));
    makeLog($intern, $enrollment, times: [
        'am_time_in' => '08:00',
        'am_time_out' => '10:30',
        'am_time_in_2' => '11:00',
    ]);

    $this->actingAs($intern)
        ->get(route('intern.dashboard'))
        ->assertOk()
        ->assertSee('Successfully timed in at 11:00 AM')
        ->assertSee('AM Time In (2) recorded today')
        ->assertSee('Welcome back');

    $this->travelBack();
});

test('an afternoon-first scan opens the PM session and leaves the morning blank', function () {
    $admin = makeStaff(['role' => 'admin']);
    $intern = makeIntern();
    makeActiveEnrollment($intern);
    $payload = $intern->scanQrPayload();

    $this->actingAs($admin);

    // The intern skipped the morning and first scans after lunch. By the clock
    // this must open PM Time In — not be mislabeled "AM Time In" the way the
    // old always-fill-the-first-empty-slot logic did.
    $this->travelTo(now()->setTime(13, 0));
    $this->postJson(route('admin.kiosk.scan'), ['code' => $payload])
        ->assertOk()
        ->assertJson(['state' => 'recorded', 'action' => 'PM Time In']);

    $log = OjtLog::where('user_id', $intern->id)->whereDate('date', today())->firstOrFail();

    expect($log->am_time_in)->toBeNull()
        ->and($log->am_time_out)->toBeNull()
        ->and(substr($log->pm_time_in, 0, 5))->toBe('13:00');

    // The next scan advances to PM Time Out — it never back-fills the empty
    // morning columns behind it.
    $this->travelTo(now()->setTime(17, 0));
    $this->postJson(route('admin.kiosk.scan'), ['code' => $payload])
        ->assertOk()
        ->assertJson(['state' => 'recorded', 'action' => 'PM Time Out']);

    $log->refresh();
    expect($log->am_time_in)->toBeNull()
        ->and($log->am_time_out)->toBeNull()
        ->and(substr($log->pm_time_out, 0, 5))->toBe('17:00');
});

test('the noon boundary decides the first scan: 11:59 opens AM, 12:00 opens PM', function () {
    $admin = makeStaff(['role' => 'admin']);
    $morning = makeIntern(['student_id' => 'T-6001']);
    $afternoon = makeIntern(['student_id' => 'T-6002']);
    makeActiveEnrollment($morning);
    makeActiveEnrollment($afternoon);

    $this->actingAs($admin);

    // One minute before noon still belongs to the morning session.
    $this->travelTo(now()->setTime(11, 59));
    $this->postJson(route('admin.kiosk.scan'), ['code' => $morning->scanQrPayload()])
        ->assertJson(['action' => 'AM Time In']);

    // Noon sharp flips to the afternoon session.
    $this->travelTo(now()->setTime(12, 0));
    $this->postJson(route('admin.kiosk.scan'), ['code' => $afternoon->scanQrPayload()])
        ->assertJson(['action' => 'PM Time In']);

    expect(OjtLog::where('user_id', $morning->id)->value('am_time_in'))->not->toBeNull();

    $afternoonLog = OjtLog::where('user_id', $afternoon->id)->firstOrFail();
    expect($afternoonLog->am_time_in)->toBeNull()
        ->and($afternoonLog->pm_time_in)->not->toBeNull();
});

test('a bare token without the OJTID prefix still resolves', function () {
    $admin = makeStaff(['role' => 'admin']);
    $intern = makeIntern();
    makeActiveEnrollment($intern);

    // Some scanner configs strip anything before a delimiter — the raw token
    // alone must still identify the intern.
    $this->travelTo(now()->setTime(8, 15));

    $this->actingAs($admin)
        ->postJson(route('admin.kiosk.scan'), ['code' => $intern->scanToken()])
        ->assertOk()
        ->assertJson(['state' => 'recorded', 'action' => 'AM Time In']);

    expect(OjtLog::where('user_id', $intern->id)->whereDate('date', today())->exists())->toBeTrue();
});

test('a scan earlier than an already-recorded time is refused', function () {
    $admin = makeStaff(['role' => 'admin']);
    $intern = makeIntern();
    $enrollment = makeActiveEnrollment($intern);
    $log = makeLog($intern, $enrollment, times: ['am_time_in' => '23:30']); // manual fix put AM in late

    $this->travelTo(now()->setTime(9, 0));

    $this->actingAs($admin)
        ->postJson(route('admin.kiosk.scan'), ['code' => $intern->scanQrPayload()])
        ->assertOk()
        ->assertJson(['state' => 'out_of_order']);

    expect($log->refresh()->am_time_out)->toBeNull();
});

/*
 * Cooldown — consecutive scans must be at least ten minutes apart, or an
 * intern (or anyone holding their code) could tap through every remaining
 * slot in seconds and log a full day of hours that were never worked.
 */

test('a scan less than ten minutes after the last punch is refused by the cooldown', function () {
    $admin = makeStaff(['role' => 'admin']);
    $intern = makeIntern();
    makeActiveEnrollment($intern);
    $payload = $intern->scanQrPayload();

    $this->actingAs($admin);

    $this->travelTo(now()->setTime(8, 0));
    $this->postJson(route('admin.kiosk.scan'), ['code' => $payload])
        ->assertJson(['state' => 'recorded', 'action' => 'AM Time In']);

    // Nine minutes later — still inside the cooldown, nothing is recorded.
    $this->travelTo(now()->setTime(8, 9));
    $this->postJson(route('admin.kiosk.scan'), ['code' => $payload])
        ->assertOk()
        ->assertJson(['state' => 'too_soon'])
        ->assertJsonPath('message', 'Intern, Test scanned less than 10 minutes ago — try again at 8:10 AM.');

    $log = OjtLog::where('user_id', $intern->id)->whereDate('date', today())->firstOrFail();

    expect($log->am_time_out)->toBeNull();

    // Ten minutes after the last punch, the next slot records normally.
    $this->travelTo(now()->setTime(8, 10));
    $this->postJson(route('admin.kiosk.scan'), ['code' => $payload])
        ->assertOk()
        ->assertJson(['state' => 'recorded', 'action' => 'AM Time Out']);

    expect(substr($log->refresh()->am_time_out, 0, 5))->toBe('08:10');
});

test('a manual Student-ID entry honors the same cooldown as a scan', function () {
    $admin = makeStaff(['role' => 'admin']);
    $intern = makeIntern(['student_id' => 'T-9999']);
    makeActiveEnrollment($intern);

    $this->actingAs($admin);

    $this->travelTo(now()->setTime(8, 0));
    $this->postJson(route('admin.kiosk.manual'), ['student_id' => 'T-9999'])
        ->assertJson(['state' => 'recorded', 'action' => 'AM Time In']);

    // Five minutes later, a second manual entry is blocked like a scan would be.
    $this->travelTo(now()->setTime(8, 5));
    $this->postJson(route('admin.kiosk.manual'), ['student_id' => 'T-9999'])
        ->assertOk()
        ->assertJson(['state' => 'too_soon']);

    expect(OjtLog::where('user_id', $intern->id)->whereDate('date', today())->value('am_time_out'))->toBeNull();
});

test('an unrecognized code is reported and records nothing', function () {
    $admin = makeStaff(['role' => 'admin']);

    $this->actingAs($admin)
        ->postJson(route('admin.kiosk.scan'), ['code' => 'OJTID:not-a-real-token'])
        ->assertOk()
        ->assertJson(['state' => 'unknown_code']);

    expect(OjtLog::count())->toBe(0);
});

test('a non-intern code is rejected', function () {
    $admin = makeStaff(['role' => 'admin']);
    $coordinator = makeStaff(['role' => 'coordinator']);

    $this->actingAs($admin)
        ->postJson(route('admin.kiosk.scan'), ['code' => $coordinator->scanQrPayload()])
        ->assertOk()
        ->assertJson(['state' => 'not_intern']);

    expect(OjtLog::count())->toBe(0);
});

test('an intern with no active set is surfaced, not recorded', function () {
    $admin = makeStaff(['role' => 'admin']);
    $noSet = makeIntern(['student_id' => 'T-3001']);
    $completed = makeIntern(['student_id' => 'T-3002']);

    OjtEnrollment::create([
        'user_id' => $completed->id,
        'label' => 'Finished OJT',
        'target_hours' => 300,
        'status' => 'completed',
        'started_at' => today()->subYear()->toDateString(),
    ]);

    $this->actingAs($admin);

    $this->postJson(route('admin.kiosk.scan'), ['code' => $noSet->scanQrPayload()])
        ->assertOk()->assertJson(['state' => 'no_set']);

    $this->postJson(route('admin.kiosk.scan'), ['code' => $completed->scanQrPayload()])
        ->assertOk()->assertJson(['state' => 'no_set']);

    expect(OjtLog::count())->toBe(0);
});

test('a deactivated intern is blocked at the kiosk', function () {
    $admin = makeStaff(['role' => 'admin']);
    $intern = makeIntern(['is_active' => false]);
    makeActiveEnrollment($intern);

    $this->actingAs($admin)
        ->postJson(route('admin.kiosk.scan'), ['code' => $intern->scanQrPayload()])
        ->assertOk()
        ->assertJson(['state' => 'inactive']);

    expect(OjtLog::count())->toBe(0);
});

test('a missing or empty code is reported as unrecognized, in JSON', function () {
    $admin = makeStaff(['role' => 'admin']);

    // The kiosk always expects a JSON reply — a bad code must not redirect or
    // 500 (this app only auto-renders JSON errors for api/* routes).
    $this->actingAs($admin)
        ->postJson(route('admin.kiosk.scan'), [])
        ->assertOk()
        ->assertJson(['state' => 'unknown_code']);

    $this->actingAs($admin)
        ->postJson(route('admin.kiosk.scan'), ['code' => '   '])
        ->assertOk()
        ->assertJson(['state' => 'unknown_code']);

    expect(OjtLog::count())->toBe(0);
});

/*
 * Manual entry — when the intern left their QR at home the desk operator types
 * their Student ID instead. It resolves the intern and runs the exact same
 * checks and slot advance as a scan, so the outcome is identical either way.
 */

test('a manual Student-ID entry records the next time like a scan', function () {
    $admin = makeStaff(['role' => 'admin']);
    $intern = makeIntern(['student_id' => 'T-7777']);
    makeActiveEnrollment($intern);

    $this->travelTo(now()->setTime(8, 1));

    $this->actingAs($admin)
        ->postJson(route('admin.kiosk.manual'), ['student_id' => 'T-7777'])
        ->assertOk()
        ->assertJson([
            'state' => 'recorded',
            'action' => 'AM Time In',
        ])
        ->assertJsonPath('intern.name', $intern->full_name);

    $log = OjtLog::where('user_id', $intern->id)->whereDate('date', today())->first();

    expect($log)->not->toBeNull()
        ->and(substr($log->am_time_in, 0, 5))->toBe('08:01')
        // Recorded on the intern's own behalf, exactly like a scan.
        ->and($log->logged_by)->toBe($intern->id)
        ->and($log->am_time_out)->toBeNull();
});

test('a manual entry with an unknown or blank Student ID records nothing', function () {
    $admin = makeStaff(['role' => 'admin']);

    $this->actingAs($admin);

    $this->postJson(route('admin.kiosk.manual'), ['student_id' => 'NOPE-404'])
        ->assertOk()->assertJson(['state' => 'unknown_code']);

    // Missing / blank field must still answer JSON, never redirect or 500.
    $this->postJson(route('admin.kiosk.manual'), [])
        ->assertOk()->assertJson(['state' => 'unknown_code']);

    $this->postJson(route('admin.kiosk.manual'), ['student_id' => '   '])
        ->assertOk()->assertJson(['state' => 'unknown_code']);

    expect(OjtLog::count())->toBe(0);
});

test('the manual entry endpoint is admin-only', function () {
    $intern = makeIntern(['student_id' => 'T-8888']);
    makeActiveEnrollment($intern);

    $this->actingAs($intern)
        ->postJson(route('admin.kiosk.manual'), ['student_id' => 'T-8888'])
        ->assertForbidden();

    expect(OjtLog::count())->toBe(0);
});

/*
 * The intern's own confirmation — after a scan at the desk, the intern's
 * dashboard mirrors what just happened via OjtLog::latest_punch, so they get a
 * clear acknowledgement even once the kiosk card has cleared.
 */

test('the latest_punch accessor reports the most recent slot, even on an afternoon-only day', function () {
    $intern = makeIntern();
    $enrollment = makeActiveEnrollment($intern);

    // No times yet — nothing to acknowledge.
    $log = makeLog($intern, $enrollment, times: []);
    expect($log->latest_punch)->toBeNull();

    // Afternoon-only day: the AM slots stay blank and PM In carries the time.
    $log->update(['pm_time_in' => '13:00']);
    $punch = $log->fresh()->latest_punch;
    expect($punch['slot'])->toBe('pm_time_in')
        ->and($punch['label'])->toBe('PM Time In')
        ->and($punch['direction'])->toBe('in')
        ->and($punch['time'])->toBe('1:00 PM');

    // A later clock-out becomes the most recent punch.
    $log->update(['pm_time_out' => '17:30']);
    $punch = $log->fresh()->latest_punch;
    expect($punch['slot'])->toBe('pm_time_out')
        ->and($punch['direction'])->toBe('out')
        ->and($punch['time'])->toBe('5:30 PM');
});

test('the intern dashboard shows a confirmation banner right after a scan', function () {
    $intern = makeIntern();
    $enrollment = makeActiveEnrollment($intern);

    // Freeze "now" a few minutes after the punch so it sits inside the
    // 30-minute "just scanned" window the banner is gated on.
    $this->travelTo(today()->setTime(14, 0));
    makeLog($intern, $enrollment, times: ['pm_time_in' => '13:55']);

    $this->actingAs($intern)
        ->get(route('intern.dashboard'))
        ->assertOk()
        ->assertSee('Successfully timed in at 1:55 PM')
        ->assertSee('PM Time In recorded today');

    $this->travelBack();
});

test('the dashboard banner explains a lunch-window PM Time In', function () {
    $intern = makeIntern();
    $enrollment = makeActiveEnrollment($intern);

    // Early out at 11:00, back at 12:30 — inside the lunch window. "Now"
    // stays inside the 30-minute freshness gate even though the note itself
    // is judged against the punch time.
    $this->travelTo(today()->setTime(12, 45));
    makeLog($intern, $enrollment, times: [
        'am_time_in' => '08:00',
        'am_time_out' => '11:00',
        'pm_time_in' => '12:30',
    ]);

    $this->actingAs($intern)
        ->get(route('intern.dashboard'))
        ->assertOk()
        ->assertSee('Successfully timed in at 12:30 PM')
        ->assertSee('Counted as PM Time In')
        ->assertSee('1:00 PM');

    $this->travelBack();
});

test('the dashboard banner skips the lunch note for a normal PM Time In', function () {
    $intern = makeIntern();
    $enrollment = makeActiveEnrollment($intern);

    $this->travelTo(today()->setTime(14, 0));
    makeLog($intern, $enrollment, times: ['pm_time_in' => '13:55']);

    $this->actingAs($intern)
        ->get(route('intern.dashboard'))
        ->assertOk()
        ->assertSee('Successfully timed in at 1:55 PM')
        ->assertDontSee('Counted as PM Time In');

    $this->travelBack();
});

test('the confirmation banner clears once the latest punch is no longer recent', function () {
    $intern = makeIntern();
    $enrollment = makeActiveEnrollment($intern);

    // Same calendar day, but the last punch was hours ago — the banner is a
    // fresh-scan acknowledgement, not an all-day status, so it must be gone.
    $this->travelTo(today()->setTime(17, 0));
    makeLog($intern, $enrollment, times: ['am_time_in' => '08:00', 'pm_time_out' => '16:00']);

    $this->actingAs($intern)
        ->get(route('intern.dashboard'))
        ->assertOk()
        ->assertDontSee('Successfully timed');

    $this->travelBack();
});

test('the intern dashboard shows no confirmation banner before any scan today', function () {
    $intern = makeIntern();
    $enrollment = makeActiveEnrollment($intern);

    // Yesterday's completed day must not leak into today's confirmation.
    makeLog($intern, $enrollment, date: today()->subDay()->toDateString(), times: [
        'am_time_in' => '08:00',
        'pm_time_out' => '17:00',
    ]);

    $this->actingAs($intern)
        ->get(route('intern.dashboard'))
        ->assertOk()
        ->assertDontSee('Successfully timed');
});

/*
 * The personal token itself — generated lazily, stable once set, unique per
 * intern, and reversible through the QR payload.
 */

test('a personal scan token is generated once and stays stable', function () {
    $intern = makeIntern();

    expect($intern->scan_token)->toBeNull();

    $first = $intern->scanToken();

    expect($first)->toHaveLength(40)
        ->and($intern->scanToken())->toBe($first)          // idempotent
        ->and($intern->fresh()->scan_token)->toBe($first); // persisted
});

test('each intern gets a distinct token', function () {
    $a = makeIntern(['student_id' => 'T-4001']);
    $b = makeIntern(['student_id' => 'T-4002']);

    expect($a->scanToken())->not->toBe($b->scanToken());
});

test('the QR payload round-trips back to its intern', function () {
    $intern = makeIntern();

    $resolved = User::fromScanPayload($intern->scanQrPayload());

    expect($resolved)->not->toBeNull()
        ->and($resolved->id)->toBe($intern->id);

    // The prefix marks it as a personal code; the payload carries the token.
    expect($intern->scanQrPayload())->toStartWith('OJTID:');
});

test('an empty or prefix-only payload resolves to no one', function () {
    makeIntern();

    expect(User::fromScanPayload(''))->toBeNull()
        ->and(User::fromScanPayload('OJTID:'))->toBeNull()
        ->and(User::fromScanPayload(null))->toBeNull();
});

/*
 * Verification captures — the kiosk's webcam grabs a frame at the instant
 * of each successful scan and uploads it with the scan (multipart), so
 * supervisors and coordinators can confirm the person behind each entry.
 * The capture is always optional: a station with no camera or a denied
 * permission still records times exactly as before.
 */

test('a scan with a capture stores the photo under the recorded slot', function () {
    Storage::fake('public');
    $admin = makeStaff(['role' => 'admin']);
    $intern = makeIntern();
    makeActiveEnrollment($intern);

    $this->travelTo(now()->setTime(8, 3));

    $this->actingAs($admin)
        ->post(route('admin.kiosk.scan'), [
            'code' => $intern->scanQrPayload(),
            'capture' => UploadedFile::fake()->image('capture.jpg', 320, 240),
        ])
        ->assertOk()
        ->assertJson(['state' => 'recorded', 'action' => 'AM Time In'])
        ->assertJsonPath('captureUrl', fn (?string $url) => $url !== null);

    $log = OjtLog::where('user_id', $intern->id)->whereDate('date', today())->firstOrFail();

    expect($log->kiosk_captures)->toBeArray()
        ->and($log->kiosk_captures['am_time_in'] ?? null)->not->toBeNull()
        ->and($log->kioskCaptureUrl('am_time_in'))->not->toBeNull()
        // One photo per scan — a later slot has no capture yet.
        ->and($log->kioskCaptureUrl('am_time_out'))->toBeNull();
});

test('a scan from a camera-less station records the time without a capture', function () {
    Storage::fake('public');
    $admin = makeStaff(['role' => 'admin']);
    $intern = makeIntern();
    makeActiveEnrollment($intern);

    $this->travelTo(now()->setTime(8, 3));

    $this->actingAs($admin)
        ->postJson(route('admin.kiosk.scan'), ['code' => $intern->scanQrPayload()])
        ->assertOk()
        ->assertJson(['state' => 'recorded'])
        ->assertJsonPath('captureUrl', null);

    expect(OjtLog::where('user_id', $intern->id)->whereDate('date', today())->firstOrFail()->kiosk_captures)->toBeNull();
});

test('a non-image capture is ignored and the scan still records', function () {
    Storage::fake('public');
    $admin = makeStaff(['role' => 'admin']);
    $intern = makeIntern();
    makeActiveEnrollment($intern);

    $this->travelTo(now()->setTime(8, 3));

    $this->actingAs($admin)
        ->post(route('admin.kiosk.scan'), [
            'code' => $intern->scanQrPayload(),
            'capture' => UploadedFile::fake()->createWithContent('capture.jpg', 'definitely not an image'),
        ])
        ->assertOk()
        ->assertJson(['state' => 'recorded'])
        ->assertJsonPath('captureUrl', null);

    expect(OjtLog::where('user_id', $intern->id)->whereDate('date', today())->firstOrFail()->kiosk_captures)->toBeNull();
});

test('a refused scan never stores a capture', function () {
    Storage::fake('public');
    $admin = makeStaff(['role' => 'admin']);
    $intern = makeIntern();
    makeActiveEnrollment($intern);
    $payload = $intern->scanQrPayload();

    $this->actingAs($admin);

    $this->travelTo(now()->setTime(8, 0));
    $this->post(route('admin.kiosk.scan'), [
        'code' => $payload,
        'capture' => UploadedFile::fake()->image('capture.jpg', 320, 240),
    ])->assertJson(['state' => 'recorded']);

    // Inside the cooldown: nothing recorded, nothing captured.
    $this->travelTo(now()->setTime(8, 5));
    $this->post(route('admin.kiosk.scan'), [
        'code' => $payload,
        'capture' => UploadedFile::fake()->image('capture.jpg', 320, 240),
    ])->assertOk()
        ->assertJson(['state' => 'too_soon'])
        ->assertJsonPath('captureUrl', null);

    $log = OjtLog::where('user_id', $intern->id)->whereDate('date', today())->firstOrFail();

    expect(count($log->kiosk_captures))->toBe(1);
});

test('a manual Student-ID entry files the capture as well', function () {
    Storage::fake('public');
    $admin = makeStaff(['role' => 'admin']);
    $intern = makeIntern(['student_id' => 'T-6666']);
    makeActiveEnrollment($intern);

    $this->travelTo(now()->setTime(8, 1));

    $this->actingAs($admin)
        ->post(route('admin.kiosk.manual'), [
            'student_id' => 'T-6666',
            'capture' => UploadedFile::fake()->image('capture.jpg', 320, 240),
        ])
        ->assertOk()
        ->assertJson(['state' => 'recorded', 'action' => 'AM Time In'])
        ->assertJsonPath('captureUrl', fn (?string $url) => $url !== null);

    $log = OjtLog::where('user_id', $intern->id)->whereDate('date', today())->firstOrFail();

    expect($log->kiosk_captures['am_time_in'] ?? null)->not->toBeNull();
});

test('supervisors see the day\'s kiosk captures on the intern log page', function () {
    Storage::fake('public');
    $office = makeOffice();
    $supervisor = makeSupervisor($office);
    $intern = makeIntern(['student_id' => 'T-9101', 'office_id' => $office->id]);
    $enrollment = makeActiveEnrollment($intern);

    makeLog($intern, $enrollment, times: ['am_time_in' => '08:00', 'pm_time_out' => '17:00']);

    $log = OjtLog::where('user_id', $intern->id)->whereDate('date', today())->firstOrFail();
    $path = 'kiosk-captures/'.$intern->id.'/'.today()->toDateString().'/am_time_in-080000.jpg';
    Storage::disk('public')->put($path, 'jpeg-bytes');
    $log->forceFill(['kiosk_captures' => ['am_time_in' => $path]])->save();

    $this->actingAs($supervisor)
        ->get(route('monitor.intern', $intern))
        ->assertOk()
        // The camera button with the capture count, and the modal behind it
        // naming the slot the capture belongs to.
        ->assertSee('Kiosk captures')
        ->assertSee('AM Time In');
});

/*
 * The captures monitoring page — one gallery of every kiosk snapshot for a
 * date, scoped the way attendance review is scoped: admins and deans
 * campus-wide, supervisors by office, coordinators by their own interns.
 */

function putCapture(OjtLog $log, string $slot, string $date): string
{
    $path = 'kiosk-captures/'.$log->user_id.'/'.$date.'/'.$slot.'-080000.jpg';
    Storage::disk('public')->put($path, 'jpeg-bytes');
    $log->forceFill(['kiosk_captures' => [$slot => $path]])->save();

    return $path;
}

test('the admin sees the day\'s captures on the monitoring page', function () {
    Storage::fake('public');
    $admin = makeStaff(['role' => 'admin']);
    $intern = makeIntern();
    $enrollment = makeActiveEnrollment($intern);
    makeLog($intern, $enrollment, times: ['am_time_in' => '08:00']);
    putCapture(OjtLog::where('user_id', $intern->id)->firstOrFail(), 'am_time_in', today()->toDateString());

    $this->actingAs($admin)
        ->get(route('admin.kiosk-captures.index'))
        ->assertOk()
        ->assertSee($intern->full_name)
        ->assertSee('AM Time In')
        ->assertSee('Kiosk Captures');
});

test('supervisors only see their own office\'s captures', function () {
    Storage::fake('public');
    $office = makeOffice();
    $supervisor = makeSupervisor($office);
    $mine = makeIntern(['student_id' => 'T-9201', 'office_id' => $office->id, 'first_name' => 'Mine', 'last_name' => 'One']);
    $stranger = makeIntern(['student_id' => 'T-9202', 'first_name' => 'Other', 'last_name' => 'Office']);
    makeLog($mine, makeActiveEnrollment($mine), times: ['am_time_in' => '08:00']);
    makeLog($stranger, makeActiveEnrollment($stranger), times: ['am_time_in' => '08:00']);
    putCapture(OjtLog::where('user_id', $mine->id)->firstOrFail(), 'am_time_in', today()->toDateString());
    putCapture(OjtLog::where('user_id', $stranger->id)->firstOrFail(), 'am_time_in', today()->toDateString());

    $this->actingAs($supervisor)
        ->get(route('admin.kiosk-captures.index'))
        ->assertOk()
        ->assertSee($mine->full_name)
        ->assertDontSee($stranger->full_name);
});

test('coordinators can open the captures page and see their own interns', function () {
    Storage::fake('public');
    $coordinator = makeCoordinator();
    $mine = makeIntern(['student_id' => 'T-9203', 'coordinator_id' => $coordinator->id, 'first_name' => 'Mine', 'last_name' => 'Two']);
    $stranger = makeIntern(['student_id' => 'T-9204', 'first_name' => 'Other', 'last_name' => 'Coordinator']);
    makeLog($mine, makeActiveEnrollment($mine), times: ['am_time_in' => '08:00']);
    makeLog($stranger, makeActiveEnrollment($stranger), times: ['am_time_in' => '08:00']);
    putCapture(OjtLog::where('user_id', $mine->id)->firstOrFail(), 'am_time_in', today()->toDateString());
    putCapture(OjtLog::where('user_id', $stranger->id)->firstOrFail(), 'am_time_in', today()->toDateString());

    $this->actingAs($coordinator)
        ->get(route('admin.kiosk-captures.index'))
        ->assertOk()
        ->assertSee($mine->full_name)
        ->assertDontSee($stranger->full_name);
});

test('the date navigator filters captures by day', function () {
    Storage::fake('public');
    $admin = makeStaff(['role' => 'admin']);
    $intern = makeIntern();
    $enrollment = makeActiveEnrollment($intern);

    $yesterday = today()->subDay()->toDateString();
    makeLog($intern, $enrollment, date: $yesterday, times: ['am_time_in' => '08:00']);
    putCapture(OjtLog::where('user_id', $intern->id)->firstOrFail(), 'am_time_in', $yesterday);

    // Default view (today) has nothing — yesterday's capture stays hidden.
    $this->actingAs($admin)
        ->get(route('admin.kiosk-captures.index'))
        ->assertOk()
        ->assertDontSee($intern->full_name);

    // Navigating to yesterday surfaces it.
    $this->actingAs($admin)
        ->get(route('admin.kiosk-captures.index', ['date' => $yesterday]))
        ->assertOk()
        ->assertSee($intern->full_name);
});

test('the captures page can be searched by name or Student ID', function () {
    Storage::fake('public');
    $admin = makeStaff(['role' => 'admin']);
    $found = makeIntern(['student_id' => 'T-9301', 'first_name' => 'Found', 'last_name' => 'One']);
    $hidden = makeIntern(['student_id' => 'T-9302', 'first_name' => 'Hidden', 'last_name' => 'Two']);
    makeLog($found, makeActiveEnrollment($found), times: ['am_time_in' => '08:00']);
    makeLog($hidden, makeActiveEnrollment($hidden), times: ['am_time_in' => '08:00']);
    putCapture(OjtLog::where('user_id', $found->id)->firstOrFail(), 'am_time_in', today()->toDateString());
    putCapture(OjtLog::where('user_id', $hidden->id)->firstOrFail(), 'am_time_in', today()->toDateString());

    $this->actingAs($admin)
        ->get(route('admin.kiosk-captures.index', ['q' => 'T-9301']))
        ->assertOk()
        ->assertSee($found->full_name)
        ->assertDontSee($hidden->full_name);
});
