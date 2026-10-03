<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * An intern's attendance request. Two kinds:
 *
 *  - correction: the scans recorded on an existing entry are wrong (or a
 *    slot is missing) — the request carries the proposed times for that
 *    day, and approval applies them to the log (hours recompute through
 *    the model's saving hook).
 *  - absence: a duty day with no entry that the intern was absent for —
 *    informational only; no log is ever created from it.
 *
 * Decided by the office supervisor or the intern's coordinator; the
 * intern is notified of the outcome either way.
 */
class LogRequest extends Model
{
    public const TYPE_CORRECTION = 'correction';

    public const TYPE_ABSENCE = 'absence';

    public const STATUS_PENDING = 'pending';

    public const STATUS_APPROVED = 'approved';

    public const STATUS_REJECTED = 'rejected';

    /**
     * The eight clock slots in day order — the ones a correction proposes.
     *
     * @var list<string>
     */
    public const TIME_FIELDS = [
        'am_time_in',
        'am_time_out',
        'am_time_in_2',
        'am_time_out_2',
        'pm_time_in',
        'pm_time_out',
        'pm_time_in_2',
        'pm_time_out_2',
    ];

    protected $fillable = [
        'intern_id',
        'ojt_log_id',
        'type',
        'date',
        ...self::TIME_FIELDS,
        'reason',
        'status',
        'decided_by',
        'decision_comment',
        'decided_at',
    ];

    protected function casts(): array
    {
        return [
            'date' => 'date',
            'decided_at' => 'datetime',
        ];
    }

    public function intern()
    {
        return $this->belongsTo(User::class, 'intern_id');
    }

    public function ojtLog()
    {
        return $this->belongsTo(OjtLog::class);
    }

    public function decidedBy()
    {
        return $this->belongsTo(User::class, 'decided_by');
    }

    /**
     * Requests still awaiting a decision.
     */
    public function scopePending($query)
    {
        return $query->where('status', self::STATUS_PENDING);
    }

    /**
     * The proposed times as a fill-array for OjtLog (only the filled
     * slots — an approval passes exactly what the intern proposed).
     *
     * @return array<string, string>
     */
    public function proposedTimes(): array
    {
        return collect(self::TIME_FIELDS)
            ->filter(fn (string $field) => $this->{$field} !== null)
            ->mapWithKeys(fn (string $field) => [$field => $this->{$field}])
            ->all();
    }

    /**
     * Human label for the request lists.
     */
    public function getTypeLabelAttribute(): string
    {
        return $this->type === self::TYPE_ABSENCE ? 'Absence report' : 'Correction';
    }

    /**
     * The decision badge for the request queues.
     *
     * @return array{label: string, class: string}
     */
    public function getStatusBadgeAttribute(): array
    {
        return match ($this->status) {
            self::STATUS_PENDING => ['label' => 'Pending', 'class' => 'bg-amber-50 text-amber-700'],
            self::STATUS_APPROVED => ['label' => 'Approved', 'class' => 'bg-emerald-50 text-emerald-700'],
            default => ['label' => 'Rejected', 'class' => 'bg-red-50 text-red-700'],
        };
    }
}
