<?php

namespace App\Notifications;

use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

/**
 * Sent to the System Admin(s) when an OJT coordinator submits a request
 * for their decision — an intern placement proposal or a completion
 * recommendation. `$kind` distinguishes the two queues.
 */
class RequestSubmitted extends Notification
{
    use Queueable;

    public function __construct(
        public string $kind,
        public int $requestId,
        public User $coordinator,
        public User $intern,
        public string $subject,
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
            'kind' => 'request_submitted',
            'request_kind' => $this->kind,
            'request_id' => $this->requestId,
            'coordinator' => $this->coordinator->full_name,
            'intern' => $this->intern->full_name,
            'intern_id' => $this->intern->id,
            'subject' => $this->subject,
        ];
    }
}
