<?php

namespace App\Support;

use App\Models\TimesheetCertification;
use App\Models\User;
use App\Notifications\TimesheetCertified;
use Illuminate\Support\Carbon;

/**
 * Timesheet certification — the office supervisor's formal per-period
 * (month) sign-off on an intern's duty hours. The record backs the
 * "Certified" state in the monitor UI and fills the certified_* merge
 * keys in the Word timesheet. Hour numbers themselves are never changed
 * by a certification — it attests to them.
 */
class TimesheetCertifier
{
    /**
     * Certify the intern's duty hours for a month (defaults to the
     * current month). The period runs the calendar month; the record
     * points at the intern's current set so the timesheet can pick up
     * the latest certification for it.
     *
     * @return array{TimesheetCertification, bool} The certification and
     *         whether it was newly created (false = already certified
     *         that period, same record returned).
     */
    public function certify(User $supervisor, User $intern, ?Carbon $month = null): array
    {
        $month ??= now()->startOfMonth();
        $periodStart = $month->copy()->startOfMonth()->toDateString();
        $periodEnd = $month->copy()->endOfMonth()->toDateString();

        $certification = TimesheetCertification::firstOrCreate(
            [
                'intern_id' => $intern->id,
                'period_start' => $periodStart,
            ],
            [
                'supervisor_id' => $supervisor->id,
                'ojt_enrollment_id' => $intern->currentEnrollment?->id,
                'period_end' => $periodEnd,
                'certified_at' => now(),
            ],
        );

        if ($certification->wasRecentlyCreated) {
            $intern->notify(new TimesheetCertified($certification));
        }

        return [$certification, $certification->wasRecentlyCreated];
    }

    /**
     * The intern's certifications, newest period first.
     */
    public function certificationsFor(User $intern)
    {
        return TimesheetCertification::where('intern_id', $intern->id)
            ->with('supervisor')
            ->orderByDesc('period_start')
            ->get();
    }
}
