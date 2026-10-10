<?php

namespace App\Notifications;

use App\Models\SubmittedDocument;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

/**
 * Tells the intern their submitted requirement document was approved or
 * rejected, with the reviewer's remarks.
 */
class DocumentReviewed extends Notification
{
    use Queueable;

    public function __construct(
        public SubmittedDocument $document,
        public string $decision,
        public string $reviewerName,
        public ?string $remarks,
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
            'kind' => 'document_reviewed',
            'document_id' => $this->document->id,
            'document' => $this->document->typeLabel(),
            'decision' => $this->decision,
            'reviewer' => $this->reviewerName,
            'remarks' => $this->remarks,
            'url' => route('intern.requirements.index'),
        ];
    }
}
