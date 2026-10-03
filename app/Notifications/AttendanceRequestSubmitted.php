<?php

namespace App\Notifications;

use App\Models\LogRequest;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

/**
 * Sent to the intern's office supervisors and coordinator when the
 * intern submits an attendance request — a correction to a recorded
 * entry, or an absence report. Whoever decides it, the intern hears the
 * outcome.
 */
class AttendanceRequestSubmitted extends Notification
{
    use Queueable;

    public function __construct(public LogRequest $logRequest, public User $intern) {}

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
            'kind' => 'attendance_request_submitted',
            'log_request_id' => $this->logRequest->id,
            'request_type' => $this->logRequest->type,
            'date' => $this->logRequest->date->toDateString(),
            'intern' => $this->intern->full_name,
            'intern_id' => $this->intern->id,
            'reason' => $this->logRequest->reason,
        ];
    }
}
