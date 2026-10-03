<?php

namespace App\Notifications;

use App\Models\OjtLog;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

/**
 * Sent to the System Admin(s) when a supervisor manually records an
 * attendance entry for one of their office's interns — such entries
 * start as pending, so the admin knows a fix is waiting in the logbook.
 */
class ManualEntrySubmitted extends Notification
{
    use Queueable;

    public function __construct(
        public OjtLog $log,
        public User $supervisor,
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
            'kind' => 'manual_entry_submitted',
            'log_id' => $this->log->id,
            'date' => $this->log->date->toDateString(),
            'intern' => $this->log->user->full_name,
            'intern_id' => $this->log->user->id,
            'hours' => (float) $this->log->hours_rendered,
            'supervisor' => $this->supervisor->full_name,
        ];
    }
}
