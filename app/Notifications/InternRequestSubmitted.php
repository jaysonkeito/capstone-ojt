<?php

namespace App\Notifications;

use App\Models\InternRequest;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

/**
 * Tells the recipient (the intern's coordinator by default) that an intern
 * sent a transfer or consultation request.
 */
class InternRequestSubmitted extends Notification
{
    use Queueable;

    public function __construct(
        public InternRequest $request,
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
            'kind' => 'intern_request_submitted',
            'request_id' => $this->request->id,
            'request_kind' => $this->request->typeLabel(),
            'mode' => $this->request->mode ? InternRequest::MODE_LABELS[$this->request->mode] : null,
            'office' => $this->request->office?->name,
            'intern' => $this->internName,
            'intern_id' => $this->request->intern_id,
            'message' => \Illuminate\Support\Str::limit($this->request->message, 160),
        ];
    }
}
