<?php

namespace App\Support;

use App\Models\OjtLog;
use App\Models\User;
use App\Notifications\LogFlagged;
use App\Notifications\LogReviewed;
use App\Notifications\ManualEntrySubmitted;
use Illuminate\Support\Facades\Notification;

/**
 * The log review workflow — the write-side of the `status` / `reviewed_by`
 * / `review_comment` / `reviewed_at` columns on ojt_logs.
 *
 *  - Supervisors approve or reject entries for interns placed at their
 *    office; a rejection always carries a comment.
 *  - Coordinators flag an entry for a second look (the entry goes back to
 *    pending with the coordinator's reason) — the office's supervisors are
 *    notified to adjudicate.
 *  - Supervisors can also record a missed scan manually for their office's
 *    interns; such entries start as pending for the admin to confirm.
 *
 * Hour totals keep counting every entry regardless of status — review is
 * oversight, not a gate (see the original review-fields migration note).
 */
class LogReview
{
    /**
     * Approve an entry on behalf of the given supervisor.
     */
    public function approve(OjtLog $log, User $reviewer, ?string $comment = null): OjtLog
    {
        return $this->record($log, $reviewer, 'approved', $comment);
    }

    /**
     * Reject an entry on behalf of the given supervisor — the comment is
     * the rejection reason and always required (enforced by the Form Request).
     */
    public function reject(OjtLog $log, User $reviewer, string $comment): OjtLog
    {
        return $this->record($log, $reviewer, 'rejected', $comment);
    }

    /**
     * A coordinator flags an entry: it goes back to pending so a supervisor
     * (or the admin) takes another look. The office's supervisors are
     * notified — falling back to the admins when the office has none, so a
     * flag is never lost.
     */
    public function flag(OjtLog $log, User $flagger, string $comment): OjtLog
    {
        $log = $this->record($log, $flagger, 'pending', $comment);

        $intern = $log->user;
        $recipients = $intern->office_id
            ? User::where('role', 'supervisor')->where('office_id', $intern->office_id)->where('is_active', true)->get()
            : collect();

        Notification::send(
            $recipients->isNotEmpty() ? $recipients : User::where('role', 'admin')->where('is_active', true)->get(),
            new LogFlagged($log, $flagger, $comment),
        );

        return $log;
    }

    /**
     * A supervisor records a missed scan for one of their office's interns.
     * The entry starts as pending and the admins are notified — the admin
     * (or the review flow) confirms it before it's treated as settled.
     * Hours are auto-computed by the model's saving hook.
     */
    public function submitManualEntry(User $supervisor, array $validated): OjtLog
    {
        $intern = User::findOrFail($validated['user_id']);

        $enrollment = $intern->currentEnrollment;
        $log = OjtLog::create([
            ...collect($validated)->except('user_id')->all(),
            'user_id' => $intern->id,
            'ojt_enrollment_id' => $enrollment?->id,
            'logged_by' => $supervisor->id,
            'status' => 'pending',
        ]);

        Notification::send(
            User::where('role', 'admin')->where('is_active', true)->get(),
            new ManualEntrySubmitted($log, $supervisor),
        );

        return $log;
    }

    /**
     * Persist the decision and tell the intern. Manual-entry pending rows
     * (created without a reviewer) keep their original recorded-by stamp
     * here: reviewed_by only ever names whoever made the latest decision.
     */
    private function record(OjtLog $log, User $reviewer, string $status, ?string $comment): OjtLog
    {
        $log->forceFill([
            'status' => $status,
            'reviewed_by' => $reviewer->id,
            'review_comment' => $comment,
            'reviewed_at' => now(),
        ])->save();

        $log->user->notify(new LogReviewed($log, $status, $comment, $reviewer));

        return $log;
    }
}
