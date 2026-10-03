<?php

namespace App\Support;

use App\Models\CompletionRecommendation;
use App\Models\LogRequest;
use App\Models\OjtLog;
use App\Models\PlacementRequest;
use App\Models\User;
use App\Notifications\AttendanceRequestDecided;
use App\Notifications\AttendanceRequestSubmitted;
use App\Notifications\RequestDecided;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Notification;

/**
 * The decision engine behind the request queues. One `decide()` call per
 * request kind applies the decision, runs the side effect of an approval
 * (the office is assigned; the OJT set is closed; a corrected entry's
 * times are updated), and notifies the requester or intern of the
 * outcome. Controllers stay thin; the side effects live here so an
 * approval can never be recorded without them.
 */
class RequestDecider
{
    public function __construct(private OjtEnrollmentService $enrollments) {}

    /**
     * File the intern's attendance request on their behalf. A correction
     * targets the day's existing entry (the request keeps a reference to
     * it); an absence report stands alone. The office supervisors and
     * the intern's coordinator are notified to decide it.
     */
    public function submitAttendanceRequest(User $intern, array $validated): LogRequest
    {
        $logRequest = LogRequest::create([
            ...$validated,
            'intern_id' => $intern->id,
            'ojt_log_id' => $validated['type'] === LogRequest::TYPE_CORRECTION
                ? OjtLog::where('user_id', $intern->id)->whereDate('date', $validated['date'])->value('id')
                : null,
        ]);

        $recipients = $intern->office_id
            ? User::where('role', 'supervisor')->where('office_id', $intern->office_id)->where('is_active', true)->get()
            : collect();

        if ($intern->coordinator_id) {
            $recipients->push(User::find($intern->coordinator_id));
        }

        $recipients = $recipients->filter()->unique('id');

        if ($recipients->isNotEmpty()) {
            Notification::send($recipients, new AttendanceRequestSubmitted($logRequest, $intern));
        }

        return $logRequest;
    }

    /**
     * The System Admin decides a coordinator's placement proposal.
     * Approval assigns the proposed office to the intern.
     */
    public function decidePlacement(PlacementRequest $request, User $admin, string $decision, ?string $comment = null): void
    {
        $this->record($request, $admin, $decision, $comment);

        if ($decision === PlacementRequest::STATUS_APPROVED) {
            $request->intern->update(['office_id' => $request->office_id]);
        }

        $request->coordinator->notify(new RequestDecided(
            'placement',
            $decision,
            $request->intern->full_name,
            $comment,
            $admin->full_name,
        ));
    }

    /**
     * The System Admin decides a coordinator's completion
     * recommendation. Approval closes the intern's current OJT set
     * through OjtEnrollmentService — the single place sets are closed.
     */
    public function decideCompletion(CompletionRecommendation $request, User $admin, string $decision, ?string $comment = null): void
    {
        $this->record($request, $admin, $decision, $comment);

        if ($decision === CompletionRecommendation::STATUS_APPROVED) {
            $this->enrollments->completeCurrentSet($request->intern);
        }

        $request->coordinator->notify(new RequestDecided(
            'completion',
            $decision,
            $request->intern->full_name,
            $comment,
            $admin->full_name,
        ));
    }

    /**
     * A supervisor or coordinator decides the intern's attendance
     * request. An approved correction applies the intern's proposed
     * times to the entry (hours recompute through the model's saving
     * hook); an approved absence records the acknowledgement only — no
     * log is ever created for it.
     */
    public function decideLogRequest(LogRequest $request, User $decider, string $decision, ?string $comment = null): void
    {
        $this->record($request, $decider, $decision, $comment);

        if ($decision === LogRequest::STATUS_APPROVED && $request->type === LogRequest::TYPE_CORRECTION) {
            $log = $request->ojtLog;

            if ($log) {
                $log->fill($request->proposedTimes())->save();
            }
        }

        $request->intern->notify(new AttendanceRequestDecided(
            $request,
            $decision,
            $comment,
            $decider->full_name,
        ));
    }

    /**
     * Stamp the decision onto the request row.
     */
    private function record(Model $request, User $decider, string $decision, ?string $comment): void
    {
        $request->forceFill([
            'status' => $decision,
            'decided_by' => $decider->id,
            'decision_comment' => $comment,
            'decided_at' => now(),
        ])->save();
    }
}
