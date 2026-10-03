<?php

namespace App\Policies;

use App\Models\User;

/**
 * Authorization for monitor-role access to an intern. The User model is
 * backed by InternPolicy (registered explicitly in AppServiceProvider
 * since auto-discovery expects a UserPolicy), and every rule here mirrors
 * the scoping the monitoring dashboards already apply:
 *
 *  - A coordinator sees exactly the interns assigned to them.
 *  - A supervisor sees exactly the interns placed at their office.
 */
class InternPolicy
{
    /**
     * Read access for the monitoring roles: dashboard listing, duty
     * history, reports, and exports for this intern.
     */
    public function monitor(User $user, User $intern): bool
    {
        if (! $intern->isIntern()) {
            return false;
        }

        if ($user->isCoordinator()) {
            return $intern->coordinator_id === $user->id;
        }

        return $user->isSupervisor()
            && $user->office_id !== null
            && $intern->office_id === $user->office_id;
    }

    /**
     * A supervisor recording a missed scan manually for an intern at
     * their office (the entry then lands in the pending review queue).
     */
    public function createLog(User $user, User $intern): bool
    {
        return $this->monitor($user, $intern);
    }

    /**
     * A supervisor certifying the intern's duty hours for a timesheet
     * period — the formal per-month sign-off.
     */
    public function certify(User $user, User $intern): bool
    {
        return $this->monitor($user, $intern);
    }
}
