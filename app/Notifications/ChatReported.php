<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

/**
 * A member reported the class chat — lands in the System Admin's inbox
 * with the reporter's message.
 */
class ChatReported extends Notification
{
    use Queueable;

    public function __construct(
        public string $chatName,
        public string $reporterName,
        public string $reason,
    ) {}

    /** @return list<string> */
    public function via(object $notifiable): array
    {
        return ['database', 'fcm'];
    }

    /** @return array<string, mixed> */
    public function toArray(object $notifiable): array
    {
        return [
            'kind' => 'chat_reported',
            'chat' => $this->chatName,
            'reporter' => $this->reporterName,
            'reason' => \Illuminate\Support\Str::limit($this->reason, 300),
        ];
    }
}
