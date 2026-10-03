<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * One OJT "set" for an intern — e.g. "Summer OJT" (300h) or a later,
 * separate "Internship" (500h). Each set has its own target hours and
 * accumulates hours from zero independently of any other set the same
 * intern has gone through.
 *
 * See App\Support\OjtEnrollmentService for how these are created/closed —
 * don't create/update these directly from controllers, so the "current
 * set" mirror columns on `users` (ojt_track, target_hours, ojt_status)
 * never drift out of sync.
 */
class OjtEnrollment extends Model
{
    protected $fillable = [
        'user_id',
        'label',
        'target_hours',
        'status',
        'started_at',
        'completed_at',
    ];

    protected function casts(): array
    {
        return [
            'target_hours' => 'integer',
            'started_at' => 'date',
            'completed_at' => 'date',
        ];
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function logs()
    {
        return $this->hasMany(OjtLog::class);
    }

    public function scopeActive($query)
    {
        return $query->where('status', 'active');
    }

    public function scopeCompleted($query)
    {
        return $query->where('status', 'completed');
    }

    /**
     * Hours logged against this specific set only — never spills over
     * from an earlier or later set the same intern has gone through.
     */
    public function getAccumulatedHoursAttribute(): float
    {
        return (float) $this->logs()->sum('hours_rendered');
    }

    public function getHoursRemainingAttribute(): float
    {
        $remaining = $this->target_hours - $this->accumulated_hours;

        return $remaining > 0 ? round($remaining, 2) : 0.0;
    }

    public function getCompletionPercentageAttribute(): float
    {
        if ($this->target_hours <= 0) {
            return 0.0;
        }

        return (float) min(round(($this->accumulated_hours / $this->target_hours) * 100, 1), 100);
    }

    public function getIsCompleteAttribute(): bool
    {
        return $this->accumulated_hours >= $this->target_hours;
    }

    public function getStatusLabelAttribute(): string
    {
        return match ($this->status) {
            'active' => 'Active',
            'completed' => 'Completed',
            default => ucfirst($this->status),
        };
    }
}
