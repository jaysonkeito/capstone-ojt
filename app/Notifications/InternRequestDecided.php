<?php

namespace App\Notifications;

use App\Models\InternRequest;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

/**
 * Tells the intern their transfer or consultation request was decided,
 * with the decision remarks.
 */
class InternRequestDecided extends Notification
{
    use Queueable;

    public function __construct(
        public InternRequest $request,
        public string $decision,
        public string $decidedByName,
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
            'kind' => 'intern_request_decided',
            'request_id' => $this->request->id,
            'request_kind' => $this->request->typeLabel(),
            'decision' => $this->decision,
            'decided_by' => $this->decidedByName,
            'remarks' => $this->remarks,
            'url' => route('intern.requests.index'),
        ];
    }
}
