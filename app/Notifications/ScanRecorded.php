<?php

namespace App\Notifications;

use App\Models\OjtLog;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

/**
 * Sent to an intern right after the kiosk records one of their punches —
 * confirms the time in/out the station just accepted, with the date, the
 * slot, and the recorded clock time.
 */
class ScanRecorded extends Notification
{
    use Queueable;

    public function __construct(
        public OjtLog $log,
        public string $slotLabel,
        public string $recordedAt,
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
            'kind' => 'scan_recorded',
            'log_id' => $this->log->id,
            'date' => $this->log->date->toDateString(),
            'slot' => $this->slotLabel,
            'recorded_at' => $this->recordedAt,
        ];
    }
}
