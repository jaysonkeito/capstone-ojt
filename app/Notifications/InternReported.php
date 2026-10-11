<?php

namespace App\Notifications;

use App\Models\InternReport;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

/**
 * A staff member reported an intern (proxy scan, fabricated entry,
 * conduct) — goes straight to the System Admin's inbox.
 */
class InternReported extends Notification
{
    use Queueable;

    public function __construct(
        public InternReport $report,
        public string $internName,
        public string $reporterName,
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
            'kind' => 'intern_reported',
            'report_id' => $this->report->id,
            'intern' => $this->internName,
            'intern_id' => $this->report->intern_id,
            'reporter' => $this->reporterName,
            'reason' => \Illuminate\Support\Str::limit($this->report->reason, 300),
        ];
    }
}
