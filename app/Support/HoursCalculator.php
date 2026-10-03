<?php

namespace App\Support;

use App\Models\OjtSetting;
use Carbon\Carbon;

/**
 * Turns a logbook-style AM IN/OUT + PM IN/OUT entry into regular vs.
 * overtime hours automatically, based on the campus-wide standard working
 * hours configured in `ojt_settings`.
 *
 * Rule: any time worked *within* the standard AM/PM windows counts as
 * regular hours. Any time clocked *outside* those windows — earlier than
 * the standard start, later than the standard end, or during the midday
 * break between AM and PM — is automatically counted as overtime. The AM
 * and PM sessions are evaluated independently against their own standard
 * window, which naturally captures early entry, break-window logging, and
 * late exit all as overtime without any extra special-casing. Nobody ever
 * has to flag OT by hand.
 */
class HoursCalculator
{
    /**
     * The (2) pairs carry a mid-session "stepped out / came back": AM In
     * 8:00 → AM Out 10:30, then AM In (2) 11:00 → AM Out (2) 12:00 means the
     * intern worked 8:00–10:30 and 11:00–12:00, with the 10:30–11:00 step-away
     * unpaid. Each interval is evaluated independently against its session's
     * standard window, so a dangling pair half (an In (2) with no Out (2))
     * contributes nothing until the admin completes it.
     *
     * @param  string|null  $amIn  'HH:MM' or 'HH:MM:SS'
     * @param  string|null  $amOut
     * @param  string|null  $amIn2
     * @param  string|null  $amOut2
     * @param  string|null  $pmIn
     * @param  string|null  $pmOut
     * @param  string|null  $pmIn2
     * @param  string|null  $pmOut2
     * @return array{regular_hours: float, overtime_hours: float, total_hours: float}
     */
    public static function compute(
        ?string $amIn,
        ?string $amOut,
        ?string $amIn2,
        ?string $amOut2,
        ?string $pmIn,
        ?string $pmOut,
        ?string $pmIn2 = null,
        ?string $pmOut2 = null,
    ): array {
        $standardHours = self::standardHours();

        $regularMinutes = 0;
        $overtimeMinutes = 0;

        // --- AM session: standard window = [am_start, am_end] ---
        foreach ([[$amIn, $amOut], [$amIn2, $amOut2]] as [$in, $out]) {
            [$reg, $ot] = self::splitSession($in, $out, $standardHours['am_start'], $standardHours['am_end']);
            $regularMinutes += $reg;
            $overtimeMinutes += $ot;
        }

        // --- PM session: standard window = [pm_start, pm_end] ---
        foreach ([[$pmIn, $pmOut], [$pmIn2, $pmOut2]] as [$in, $out]) {
            [$reg, $ot] = self::splitSession($in, $out, $standardHours['pm_start'], $standardHours['pm_end']);
            $regularMinutes += $reg;
            $overtimeMinutes += $ot;
        }

        return [
            'regular_hours' => round($regularMinutes / 60, 2),
            'overtime_hours' => round($overtimeMinutes / 60, 2),
            'total_hours' => round(($regularMinutes + $overtimeMinutes) / 60, 2),
        ];
    }

    /**
     * Splits a single clocked session against its standard window: the
     * overlap with [standardStart, standardEnd] is regular; everything
     * else in the session (before standardStart AND/OR after standardEnd)
     * is overtime.
     *
     * @return array{0: int, 1: int} [regularMinutes, overtimeMinutes]
     */
    private static function splitSession(?string $actualIn, ?string $actualOut, string $standardStart, string $standardEnd): array
    {
        if (! $actualIn || ! $actualOut) {
            return [0, 0];
        }

        $in = self::time($actualIn);
        $out = self::time($actualOut);

        if (! $out->gt($in)) {
            return [0, 0];
        }

        $stdStart = self::time($standardStart);
        $stdEnd = self::time($standardEnd);

        $totalMinutes = $in->diffInMinutes($out);

        $overlapStart = $in->gt($stdStart) ? $in : $stdStart;
        $overlapEnd = $out->lt($stdEnd) ? $out : $stdEnd;

        $regularMinutes = $overlapEnd->gt($overlapStart) ? $overlapStart->diffInMinutes($overlapEnd) : 0;
        $overtimeMinutes = $totalMinutes - $regularMinutes;

        return [$regularMinutes, $overtimeMinutes];
    }

    /**
     * @return array{am_start: string, am_end: string, pm_start: string, pm_end: string}
     */
    private static function standardHours(): array
    {
        $settings = OjtSetting::current();

        return [
            'am_start' => $settings->am_time_in,
            'am_end' => $settings->am_time_out,
            'pm_start' => $settings->pm_time_in,
            'pm_end' => $settings->pm_time_out,
        ];
    }

    private static function time(string $value): Carbon
    {
        return Carbon::createFromFormat(strlen($value) > 5 ? 'H:i:s' : 'H:i', $value);
    }
}
