<?php

namespace App\Policies;

use App\Models\OjtLog;
use App\Models\User;

/**
 * Authorization for the log review workflow (auto-discovered by naming
 * convention). The admin's own logbook routes are role-middleware guarded
 * and untouched by this policy — these abilities cover the monitor roles:
 *
 *  - Supervisors review (approve/reject) entries of interns at their office.
 *  - Coordinators flag entries of interns assigned to them for a second look.
 *  - Both may open an intern's Daily Report for entries in their scope.
 */
class OjtLogPolicy
{
    /**
     * Approve or reject the entry — supervisors of the intern's office.
     */
    public function review(User $user, OjtLog $log): bool
    {
        return $user->supervisesOffice($log->user->office_id);
    }

    /**
     * Flag the entry for a second look — the intern's own coordinator.
     */
    public function flag(User $user, OjtLog $log): bool
    {
        return $user->isCoordinator()
            && $log->user->coordinator_id === $user->id;
    }

    /**
     * Open the entry's Daily Report PDF — the admin (any intern) plus the
     * monitor roles within their scope.
     */
    public function viewDailyReport(User $user, OjtLog $log): bool
    {
        if ($user->isAdmin()) {
            return true;
        }

        if ($user->isCoordinator()) {
            return $log->user->coordinator_id === $user->id;
        }

        return $user->supervisesOffice($log->user->office_id);
    }
}
