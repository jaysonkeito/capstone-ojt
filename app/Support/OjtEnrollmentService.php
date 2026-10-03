<?php

namespace App\Support;

use App\Models\OjtEnrollment;
use App\Models\User;
use Illuminate\Validation\ValidationException;

/**
 * The single place that creates and closes an intern's OJT "sets"
 * (App\Models\OjtEnrollment). Controllers should always go through here
 * rather than writing to `ojt_enrollments` or the `users` mirror columns
 * (ojt_track, target_hours, ojt_status) directly, so the two never drift
 * out of sync.
 *
 * Business rules enforced here:
 *  - An intern can only have one *open* (non-completed) set at a time —
 *    starting a new one requires the current set to already be
 *    `completed` (or for the intern to have no set at all yet).
 *  - A new set starts active immediately — the admin creates it directly,
 *    so there is no approval step anymore.
 *  - A new set's hour count starts at zero — `ojt_logs` rows are scoped
 *    to a specific `ojt_enrollment_id`, so nothing from an earlier set
 *    ever counts toward this one.
 */
class OjtEnrollmentService
{
    /**
     * Start a brand-new OJT set for an intern. Throws a validation error
     * (safe to let bubble up to a 422/redirect-back-with-errors) if the
     * intern's current set isn't completed yet.
     */
    public function startNewSet(User $intern, string $label, int $targetHours): OjtEnrollment
    {
        abort_unless($intern->isIntern(), 404);

        $current = $intern->currentEnrollment;

        if ($current && $current->status !== 'completed') {
            throw ValidationException::withMessages([
                'label' => "{$intern->full_name} still has an open \"{$current->label}\" set ({$current->status_label}). Mark it Completed before starting a new one.",
            ]);
        }

        $enrollment = OjtEnrollment::create([
            'user_id' => $intern->id,
            'label' => $label,
            'target_hours' => $targetHours,
            'status' => 'active',
            'started_at' => now()->toDateString(),
        ]);

        $intern->update([
            'ojt_track' => $this->trackTypeFor($label),
            'target_hours' => $targetHours,
            'ojt_status' => 'active',
        ]);

        return $enrollment;
    }

    /**
     * Mark the intern's current set as Completed. Locks it from receiving
     * any further logged hours.
     */
    public function completeCurrentSet(User $intern): OjtEnrollment
    {
        $enrollment = $intern->currentEnrollment;

        abort_unless($enrollment, 422, "{$intern->full_name} doesn't have an OJT set yet.");

        $enrollment->update([
            'status' => 'completed',
            'completed_at' => now()->toDateString(),
        ]);

        $intern->update(['ojt_status' => 'completed']);

        return $enrollment;
    }

    /**
     * General-purpose edit of the intern's CURRENT set (label, target
     * hours, status) — used by the regular Admin "Edit Intern" form to
     * fix mistakes on an already-open (or already completed) set. This is
     * intentionally separate from startNewSet(): it never creates a new
     * `ojt_enrollments` row, so it can't be used to sneak a Summer set
     * into becoming an Internship set with all its hours intact — for
     * that, Admin must use "Start New Set", which resets the hour count
     * to zero on purpose.
     *
     * If the intern has no enrollment yet at all (shouldn't normally
     * happen post-backfill), one is created here so the mirror columns
     * always have somewhere to point.
     */
    public function updateCurrentSet(User $intern, array $attributes): OjtEnrollment
    {
        $enrollment = $intern->currentEnrollment;

        if ($enrollment) {
            $enrollment->update($attributes);
        } else {
            $enrollment = OjtEnrollment::create([
                'user_id' => $intern->id,
                'started_at' => now()->toDateString(),
                ...$attributes,
            ]);
        }

        $intern->update([
            'ojt_track' => $this->trackTypeFor($attributes['label'] ?? $enrollment->label),
            'target_hours' => $enrollment->target_hours,
            'ojt_status' => $enrollment->status,
        ]);

        return $enrollment;
    }

    /**
     * Best-effort mapping from a set's display label back to the
     * controlled `internship` | `custom` vocabulary that the
     * existing track filter dropdowns (Admin intern lists) already know
     * how to query against.
     */
    private function trackTypeFor(string $label): string
    {
        return match (true) {
            str_contains(strtolower($label), 'internship') => 'internship',
            default => 'custom',
        };
    }
}
