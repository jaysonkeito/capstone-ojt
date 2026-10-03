<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * The office supervisor's formal per-period (month) sign-off on an
 * intern's duty hours. One row per intern per period — a re-certification
 * of the same period replaces nothing; the unique (intern_id,
 * period_start) constraint simply refuses it. Feeds the "Certified"
 * state in the monitor UI and the certified_* merge keys in the Word
 * timesheet.
 */
class TimesheetCertification extends Model
{
    protected $fillable = [
        'intern_id',
        'supervisor_id',
        'ojt_enrollment_id',
        'period_start',
        'period_end',
        'certified_at',
    ];

    protected function casts(): array
    {
        return [
            'period_start' => 'date',
            'period_end' => 'date',
            'certified_at' => 'datetime',
        ];
    }

    public function intern()
    {
        return $this->belongsTo(User::class, 'intern_id');
    }

    public function supervisor()
    {
        return $this->belongsTo(User::class, 'supervisor_id');
    }

    public function ojtEnrollment()
    {
        return $this->belongsTo(OjtEnrollment::class);
    }

    /**
     * "March 2026" — the period this certification covers.
     */
    public function getPeriodLabelAttribute(): string
    {
        return $this->period_start->format('F Y');
    }
}
