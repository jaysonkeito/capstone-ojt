<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

/**
 * Sent to the requesting OJT coordinator when the System Admin decides
 * one of their requests (placement proposal or completion
 * recommendation). A rejection always carries the admin's reason.
 */
class RequestDecided extends Notification
{
    use Queueable;

    public function __construct(
        public string $kind,
        public string $decision,
        public string $internName,
        public ?string $comment,
        public string $deciderName,
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
            'kind' => 'request_decided',
            'request_kind' => $this->kind,
            'decision' => $this->decision,
            'intern' => $this->internName,
            'comment' => $this->comment,
            'decided_by' => $this->deciderName,
        ];
    }
}
