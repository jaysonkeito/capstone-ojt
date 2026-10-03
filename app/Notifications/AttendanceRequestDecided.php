<?php

namespace App\Notifications;

use App\Models\LogRequest;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

/**
 * Sent to the intern when their attendance request is decided. An
 * approved correction has already been applied to the log; a rejection
 * carries the decider's reason.
 */
class AttendanceRequestDecided extends Notification
{
    use Queueable;

    public function __construct(
        public LogRequest $logRequest,
        public string $decision,
        public ?string $comment,
        public string $deciderName,
    ) {}

    /**
     * @return list<string>
     */
    public function via(object $notifiable): array
    {
        return ['database', 'fcm'];
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return [
            'kind' => 'attendance_request_decided',
            'log_request_id' => $this->logRequest->id,
            'request_type' => $this->logRequest->type,
            'date' => $this->logRequest->date->toDateString(),
            'decision' => $this->decision,
            'comment' => $this->comment,
            'decided_by' => $this->deciderName,
        ];
    }
}
