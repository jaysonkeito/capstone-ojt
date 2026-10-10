<?php

namespace App\Notifications;

use App\Models\SubmittedDocument;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

/**
 * Tells the coordinator (or reviewer) that an intern submitted a
 * requirement document for their review.
 */
class DocumentSubmitted extends Notification
{
    use Queueable;

    public function __construct(
        public SubmittedDocument $document,
        public string $internName,
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
            'kind' => 'document_submitted',
            'document_id' => $this->document->id,
            'document' => $this->document->typeLabel(),
            'intern' => $this->internName,
            'intern_id' => $this->document->user_id,
        ];
    }
}
