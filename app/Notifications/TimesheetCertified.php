<?php

namespace App\Notifications;

use App\Models\TimesheetCertification;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

/**
 * Sent to an intern when the office supervisor formally certifies one of
 * their timesheet periods — the certified block in the Word timesheet
 * fills from that record.
 */
class TimesheetCertified extends Notification
{
    use Queueable;

    public function __construct(public TimesheetCertification $certification) {}

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
            'kind' => 'timesheet_certified',
            'period' => $this->certification->period_label,
            'certified_by' => $this->certification->supervisor?->full_name ?? 'your supervisor',
            'certified_at' => $this->certification->certified_at->toDateTimeString(),
        ];
    }
}
