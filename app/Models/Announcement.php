<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * A staff announcement to interns. Who sees it is fixed at creation —
 * the author's role picks the audience (admin → everyone, dean/chair →
 * their college, supervisor → their office, coordinator → their class)
 * and the scope is snapshotted onto the row, so later account changes
 * don't rewrite who an old announcement reached.
 */
class Announcement extends Model
{
    protected $fillable = [
        'author_id',
        'audience',
        'college_code',
        'office_id',
        'coordinator_id',
        'title',
        'body',
        'starts_at',
        'ends_at',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'starts_at' => 'date',
            'ends_at' => 'date',
        ];
    }

    /**
     * Posts currently visible on the dashboard banner: active, inside
     * their display window — started (or with no start) and not yet ended
     * (or with no end). The window is what lets an announcement retire
     * itself instead of waiting to be deleted.
     */
    public function scopeCurrentlyShowing($query)
    {
        return $query->where('is_active', true)
            ->where(fn ($q) => $q->whereNull('starts_at')->orWhere('starts_at', '<=', today()))
            ->where(fn ($q) => $q->whereNull('ends_at')->orWhere('ends_at', '>=', today()));
    }

    /**
     * Where the post sits in its window right now — drives the status
     * chip on the staff-side list.
     */
    public function getScheduleStateAttribute(): string
    {
        if (! $this->is_active) {
            return 'inactive';
        }

        if ($this->starts_at?->isFuture()) {
            return 'scheduled';
        }

        if ($this->ends_at?->endOfDay()->isPast()) {
            return 'ended';
        }

        return 'showing';
    }

    public const AUDIENCE_LABELS = [
        'all' => 'All interns',
        'college' => 'My college',
        'office' => 'My office',
        'class' => 'My class',
    ];

    public function author()
    {
        return $this->belongsTo(User::class, 'author_id');
    }

    public function office()
    {
        return $this->belongsTo(Office::class);
    }

    /**
     * The interns this announcement targets — the snapshot re-read live.
     */
    public function interns()
    {
        return User::where('role', 'intern')->where('is_active', true)
            ->when($this->audience === 'office', fn ($q) => $q->where('office_id', $this->office_id))
            ->when($this->audience === 'class', fn ($q) => $q->where('coordinator_id', $this->coordinator_id))
            ->when($this->audience === 'college', fn ($q) => $q->whereHas('coordinator', fn ($cq) => $cq
                ->where('college_code', $this->college_code)
                ->orWhereHas('staffProfile', fn ($sq) => $sq->where('college_code', $this->college_code))));
    }

    public function auditLabel(): string
    {
        return $this->title;
    }
}
