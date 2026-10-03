<?php

namespace App\Notifications;

use App\Models\OjtLog;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

/**
 * Sent to the supervisors of an intern's office (falling back to the
 * System Admin when the office has none) when the intern's OJT
 * coordinator flags an entry for a second look — the entry moves to
 * pending until a supervisor approves or rejects it.
 */
class LogFlagged extends Notification
{
    use Queueable;

    public function __construct(
        public OjtLog $log,
        public User $flagger,
        public string $comment,
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
            'kind' => 'log_flagged',
            'log_id' => $this->log->id,
            'date' => $this->log->date->toDateString(),
            'intern' => $this->log->user->full_name,
            'intern_id' => $this->log->user->id,
            'comment' => $this->comment,
            'flagged_by' => $this->flagger->full_name,
        ];
    }
}
