<?php

namespace App\Policies;

use App\Models\LogRequest;
use App\Models\User;

/**
 * Who may decide an intern's attendance request (auto-discovered by
 * naming convention). The people who can actually witness the day —
 * the office supervisor and the intern's coordinator — plus the admin
 * as the fallback adjudicator.
 */
class LogRequestPolicy
{
    public function decide(User $user, LogRequest $logRequest): bool
    {
        if ($user->isAdmin()) {
            return true;
        }

        return $user->monitors($logRequest->intern);
    }

    /**
     * A request may be withdrawn by the intern who filed it while it still
     * awaits a decision — once a supervisor has ruled, the outcome is part
     * of the record and the line can't be erased.
     */
    public function delete(User $user, LogRequest $logRequest): bool
    {
        return $user->isIntern()
            && $logRequest->intern_id === $user->id
            && $logRequest->status === 'pending';
    }
}
