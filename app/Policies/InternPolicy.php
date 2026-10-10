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
        return $user->monitors($intern);
    }

    /**
     * Recording a missed scan manually for an intern — the office
     * supervisor's task, whoever holds that office (supervisor,
     * coordinator-supervisor, dean- or chair-supervisor). Oversight
     * without an office never writes entries.
     */
    public function createLog(User $user, User $intern): bool
    {
        return $user->supervisesOffice($intern->office_id);
    }

    /**
     * Certifying the intern's duty hours for a timesheet period — the
     * formal per-month sign-off, same office-supervision gate.
     */
    public function certify(User $user, User $intern): bool
    {
        return $user->supervisesOffice($intern->office_id);
    }
}
