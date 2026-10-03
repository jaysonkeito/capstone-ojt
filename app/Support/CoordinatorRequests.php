<?php

namespace App\Support;

use App\Models\CompletionRecommendation;
use App\Models\PlacementRequest;
use App\Models\User;
use App\Notifications\RequestSubmitted;
use Illuminate\Support\Facades\Notification;

/**
 * The requests an OJT coordinator can raise about their interns:
 *
 *  - a placement proposal (which office the intern should be assigned to)
 *  - a completion recommendation (the intern reached their target hours)
 *
 * Both land in the System Admin's queue. One *pending* request of each
 * kind per intern at a time — the admin decides one before another can
 * be filed, so a queue entry never goes stale against its intern.
 */
class CoordinatorRequests
{
    /**
     * Propose an office for the coordinator's intern.
     */
    public function requestPlacement(User $coordinator, User $intern, int $officeId, ?string $note = null): PlacementRequest
    {
        $request = PlacementRequest::create([
            'intern_id' => $intern->id,
            'coordinator_id' => $coordinator->id,
            'office_id' => $officeId,
            'note' => $note,
            'status' => PlacementRequest::STATUS_PENDING,
        ]);

        $this->notifyAdmins('placement', $request->id, $coordinator, $intern,
            "placement at {$request->office->name}");

        return $request;
    }

    /**
     * Recommend that the coordinator's intern (who has reached their
     * target hours) be marked as having completed the OJT set.
     */
    public function recommendCompletion(User $coordinator, User $intern, ?string $note = null): CompletionRecommendation
    {
        $request = CompletionRecommendation::create([
            'intern_id' => $intern->id,
            'coordinator_id' => $coordinator->id,
            'note' => $note,
            'status' => CompletionRecommendation::STATUS_PENDING,
        ]);

        $this->notifyAdmins('completion', $request->id, $coordinator, $intern,
            'completion of their OJT set');

        return $request;
    }

    /**
     * Whether the intern already has a pending request of this kind.
     */
    public function hasPending(string $kind, User $intern): bool
    {
        return $kind === 'placement'
            ? PlacementRequest::where('intern_id', $intern->id)->pending()->exists()
            : CompletionRecommendation::where('intern_id', $intern->id)->pending()->exists();
    }

    private function notifyAdmins(string $kind, int $requestId, User $coordinator, User $intern, string $subject): void
    {
        Notification::send(
            User::where('role', 'admin')->where('is_active', true)->get(),
            new RequestSubmitted($kind, $requestId, $coordinator, $intern, $subject),
        );
    }
}
