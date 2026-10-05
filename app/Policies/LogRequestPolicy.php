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

        if ($user->isCoordinator()) {
            return $logRequest->intern->coordinator_id === $user->id;
        }

        return $user->supervisesOffice($logRequest->intern->office_id);
    }
}
