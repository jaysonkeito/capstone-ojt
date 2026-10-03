<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * An OJT coordinator's formal recommendation that an intern (whose hours
 * reached the target) be marked as having completed the OJT set. The
 * System Admin's approval closes the current set through
 * OjtEnrollmentService — the single place sets are closed — and notifies
 * the coordinator.
 */
class CompletionRecommendation extends Model
{
    public const STATUS_PENDING = 'pending';

    public const STATUS_APPROVED = 'approved';

    public const STATUS_REJECTED = 'rejected';

    protected $fillable = [
        'intern_id',
        'coordinator_id',
        'note',
        'status',
        'decided_by',
        'decision_comment',
        'decided_at',
    ];

    protected function casts(): array
    {
        return [
            'decided_at' => 'datetime',
        ];
    }

    public function intern()
    {
        return $this->belongsTo(User::class, 'intern_id');
    }

    public function coordinator()
    {
        return $this->belongsTo(User::class, 'coordinator_id');
    }

    public function decidedBy()
    {
        return $this->belongsTo(User::class, 'decided_by');
    }

    /**
     * Requests still awaiting the admin's decision.
     */
    public function scopePending($query)
    {
        return $query->where('status', self::STATUS_PENDING);
    }

    /**
     * Filter by decision state (pending/approved/rejected; null = all).
     */
    public function scopeStatus($query, ?string $status)
    {
        return in_array($status, [self::STATUS_PENDING, self::STATUS_APPROVED, self::STATUS_REJECTED], true)
            ? $query->where('status', $status)
            : $query;
    }

    /**
     * The admin's decision badge for the request queues.
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
