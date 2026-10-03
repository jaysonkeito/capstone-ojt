<?php

namespace App\Support;

use App\Models\OjtEnrollment;
use App\Models\OjtLog;
use App\Models\OjtSetting;
use App\Models\User;
use Illuminate\Support\Carbon;

/**
 * The one place that turns a scan into a recorded time. Every way an intern's
 * attendance is captured at the office desk scanner (the kiosk) — a QR scan, a
 * webcam read, or a manual Student-ID entry — funnels through here, so slot
 * selection and the forward-only guard behave identically no matter how the
 * scan arrived. The first scan of the day opens the AM or PM session by the
 * clock (noon boundary); later scans advance from the last filled slot.
 *
 * Stepping out early and coming back: the report's spine is still the four
 * slots (AM In → AM Out → PM In → PM Out), and each session additionally has
 * a "(2)" gap pair — AM In (2) → AM Out (2), PM In (2) → PM Out (2) — that
 * records ONE stepped-out-and-returned episode per session. An early out
 * (a scan before the next boundary) closes the main pair; the intern's next
 * scan is then judged by the clock: before the boundary (the session's own
 * standard end — noon for the morning) it steps back into the session (fills
 * In (2)); at or after it — including a 12:00–1:00 PM return, which already
 * counts as PM Time In rather than a step back into the morning — the scan
 * opens the next slot instead, leaving the gap pair dangling for the admin
 * to complete. The unpaid step-away window between the main out and In (2)
 * is simply not counted (see HoursCalculator).
 */
class AttendanceRecorder
{
    /**
     * The report spine — the four slots a duty day has always had, in order.
     *
     * @var array<string, string>
     */
    public const SLOTS = [
        'am_time_in' => 'AM Time In',
        'am_time_out' => 'AM Time Out',
        'pm_time_in' => 'PM Time In',
        'pm_time_out' => 'PM Time Out',
    ];

    /**
     * The mid-session "stepped out / came back" slots each session's main pair
     * can be followed by, mapped to their kiosk labels. A session's four
     * columns read in day order: In → Out → In (2) → Out (2).
     *
     * @var array<string, array<string, string>>
     */
    public const STEP_BACK_SLOTS = [
        'am' => [
            'am_time_in_2' => 'AM Time In (2)',
            'am_time_out_2' => 'AM Time Out (2)',
        ],
        'pm' => [
            'pm_time_in_2' => 'PM Time In (2)',
            'pm_time_out_2' => 'PM Time Out (2)',
        ],
    ];

    /**
     * Every slot a scan can fill, in the order a normal day fills them —
     * the spine plus both gap pairs.
     *
     * @return list<string>
     */
    public static function slotOrder(): array
    {
        return [
            'am_time_in',
            'am_time_out',
            ...array_keys(self::STEP_BACK_SLOTS['am']),
            'pm_time_in',
            'pm_time_out',
            ...array_keys(self::STEP_BACK_SLOTS['pm']),
        ];
    }

    /**
     * The kiosk label for any slot, spine or gap.
     */
    public static function labelFor(string $slot): ?string
    {
        return self::SLOTS[$slot]
            ?? self::STEP_BACK_SLOTS['am'][$slot]
            ?? self::STEP_BACK_SLOTS['pm'][$slot]
            ?? null;
    }

    /**
     * Minutes that must pass between consecutive punches. Without it a scan
     * after every slot is already recorded can be spammed in and out within
     * seconds, manufacturing hours that were never worked.
     *
     * @var int
     */
    public const COOLDOWN_MINUTES = 10;

    /**
     * Record the given clock time on the intern's day (creating today's entry
     * on the first scan) and report what happened so the caller can render it
     * as JSON for the kiosk.
     *
     * @return array{state: string, log: OjtLog, slot: ?string}
     */
    public static function record(User $intern, OjtEnrollment $enrollment, Carbon $when): array
    {
        $time = $when->format('H:i');

        $log = OjtLog::where('user_id', $intern->id)
            ->whereDate('date', $when->toDateString())
            ->first();

        // First scan of the day — decide which session it opens by the clock
        // instead of always assuming morning. An intern who skips the morning
        // and first scans in the afternoon opens the PM session (PM Time In)
        // and leaves the morning columns blank, rather than mislabeling, say,
        // 1 PM as "AM Time In". Noon is the boundary: before 12:00 opens AM,
        // 12:00 or later opens PM.
        if (! $log) {
            $slot = $when->hour < 12 ? 'am_time_in' : 'pm_time_in';

            $log = OjtLog::create([
                'user_id' => $intern->id,
                'ojt_enrollment_id' => $enrollment->id,
                'date' => $when->toDateString(),
                $slot => $time,
                'logged_by' => $intern->id,
            ]);

            return ['state' => 'recorded', 'log' => $log, 'slot' => $slot];
        }

        $settings = OjtSetting::current();
        $amEnd = substr((string) $settings->am_time_out, 0, 5);
        $pmStart = substr((string) $settings->pm_time_in, 0, 5);
        $pmEnd = substr((string) $settings->pm_time_out, 0, 5);

        // The day is full once even the last gap slot carries a time.
        if (self::lastFilledSlot($log) === 'pm_time_out_2') {
            return ['state' => 'done', 'log' => $log, 'slot' => null];
        }

        // Guard: scans must move the day forward. A new time at or before the
        // latest already-recorded time means something is off (clock drift, a
        // double scan after a manual edit) — surface it for the admin to
        // correct instead of silently saving an impossible order.
        $latestFilled = self::latestFilledTime($log);

        if ($latestFilled && $time <= $latestFilled) {
            return ['state' => 'out_of_order', 'log' => $log, 'slot' => null];
        }

        // Cooldown: scans must also be spaced out. An intern (or someone
        // holding their code) can otherwise tap through the remaining slots
        // seconds apart and log a full day's hours that were never worked.
        if ($latestFilled) {
            $lastPunch = $when->copy()->setTimeFromTimeString($latestFilled);

            if ($when->lt($lastPunch->addMinutes(self::COOLDOWN_MINUTES))) {
                return ['state' => 'too_soon', 'log' => $log, 'slot' => null];
            }
        }

        // Which slot comes next, and the clock conditions around it:
        //
        // $notAfter — the slot is only offered while the session is still
        // live (the morning until the AM window ends at noon, the afternoon
        // until the standard day end). A scan past that boundary falls
        // through to $fallback instead — the next spine slot — or, with no
        // fallback, the day is simply done. Either way any dangling gap pair
        // is left for the admin to complete.
        //        // $notBefore — the slot is only offered from a given time on (used
        // when the morning is fully closed and only the PM opener remains:
        // a scan before the lunch break would be a second mid-morning
        // excursion, which the one gap pair cannot represent).
        [$slot, $notAfter, $fallback, $notBefore] = match (self::lastFilledSlot($log)) {
            'am_time_in' => ['am_time_out', null, null, null],
            // The morning session ends at the AM window end (noon), so a
            // scan from then on is already the afternoon: a time in between
            // noon and the PM window (12:00–1:00 on the standard schedule)
            // counts as PM Time In, not a step back into the morning.
            'am_time_out' => ['am_time_in_2', $amEnd, 'pm_time_in', null],
            'am_time_in_2' => ['am_time_out_2', $amEnd, 'pm_time_in', null],
            'am_time_out_2' => ['pm_time_in', null, null, $amEnd],
            'pm_time_in' => ['pm_time_out', null, null, null],
            'pm_time_out' => ['pm_time_in_2', $pmEnd, null, null],
            // The final departure is recorded whenever it happens — stepping
            // back in after an early out and leaving after the standard day
            // end is normal overtime, not out of bounds.
            'pm_time_in_2' => ['pm_time_out_2', null, null, null],
            // A log exists but has no times (admin created it bare) — open
            // the session the same way a first scan would.
            default => [$when->hour < 12
                ? 'am_time_in'
                : 'pm_time_in', null, null, null],
        };

        if ($notBefore !== null && $time < $notBefore) {
            return ['state' => 'done', 'log' => $log, 'slot' => null];
        }

        if ($notAfter !== null && $time >= $notAfter) {
            if ($fallback === null) {
                return ['state' => 'done', 'log' => $log, 'slot' => null];
            }

            $slot = $fallback;
        }

        return self::writeSlot($log, $slot, $time, $intern);
    }

    /**
     * Persist one slot time and report it as recorded.
     *
     * @param  string  $slot  any key of SLOTS or STEP_BACK_SLOTS
     * @return array{state: string, log: OjtLog, slot: string}
     */
    private static function writeSlot(OjtLog $log, string $slot, string $time, User $intern): array
    {
        $log->fill([$slot => $time, 'logged_by' => $intern->id])->save();

        return ['state' => 'recorded', 'log' => $log, 'slot' => $slot];
    }

    /**
     * The last slot in day order that carries a time — the frontier the next
     * scan advances from — or null when the entry has no times yet.
     */
    private static function lastFilledSlot(OjtLog $log): ?string
    {
        $last = null;

        foreach (self::slotOrder() as $slot) {
            if ($log->{$slot}) {
                $last = $slot;
            }
        }

        return $last;
    }

    /**
     * Latest of the slot times already on the entry, 'H:i' — or null when
     * none are filled yet. Covers gap slots too, so a step back in also
     * starts the cooldown and the forward-only clock.
     */
    public static function latestFilledTime(OjtLog $log): ?string
    {
        return collect(self::slotOrder())
            ->filter(fn ($slot) => $log->{$slot})
            ->map(fn ($slot) => $log->{$slot})
            ->max();
    }

    /**
     * The explanation for a time in during the lunch window — from noon,
     * when the morning session ends, until the PM window opens — where the
     * scan is recorded as PM Time In even though the afternoon hasn't
     * formally started. Null outside that window. The kiosk result card and
     * the intern dashboard banner both show it so the PM label doesn't look
     * like a mistake to someone back before the afternoon starts.
     */
    public static function lunchWindowNote(Carbon $when): ?string
    {
        if ($when->lt($when->copy()->setTime(12, 0))) {
            return null;
        }

        $pmStart = $when->copy()
            ->setTimeFromTimeString(substr((string) OjtSetting::current()->pm_time_in, 0, 5));

        if ($when->gte($pmStart)) {
            return null;
        }

        return 'Counted as PM Time In — the morning ends at noon, so a scan from 12:00 PM to '
            .$pmStart->format('g:i A').' opens the afternoon.';
    }
}
