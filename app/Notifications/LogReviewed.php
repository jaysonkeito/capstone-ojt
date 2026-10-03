<?php

namespace App\Notifications;

use App\Models\OjtLog;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

/**
 * Sent to an intern when a supervisor approves or rejects one of their
 * duty entries. A rejection always carries the supervisor's comment so
 * the intern knows what to fix or dispute.
 */
class LogReviewed extends Notification
{
    use Queueable;

    public function __construct(
        public OjtLog $log,
        public string $decision,
        public ?string $comment,
        public User $reviewer,
    ) {}

    /**
     * @return list<string>
     */
    public function via(object $notifiable): array
    {
        return ['database'];
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return [
            'kind' => 'log_reviewed',
            'log_id' => $this->log->id,
            'date' => $this->log->date->toDateString(),
            'decision' => $this->decision,
            'comment' => $this->comment,
            'reviewer' => $this->reviewer->full_name,
        ];
    }
}
