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
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
        ];
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
