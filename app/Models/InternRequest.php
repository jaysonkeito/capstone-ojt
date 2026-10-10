<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * An intern-initiated request to their coordinator: applying to another
 * office, or asking for a consultation (online or face-to-face). The
 * coordinator decides with remarks; an approved office transfer moves
 * the placement on the spot, audited as a normal intern update.
 */
class InternRequest extends Model
{
    protected $fillable = [
        'intern_id',
        'type',
        'office_id',
        'mode',
        'recipient_id',
        'message',
        'status',
        'decision_remarks',
        'decided_by',
        'decided_at',
    ];

    protected function casts(): array
    {
        return [
            'decided_at' => 'datetime',
        ];
    }

    public const TYPE_LABELS = [
        'office_transfer' => 'Office transfer',
        'consultation' => 'Consultation',
    ];

    public const MODE_LABELS = [
        'online' => 'Online',
        'f2f' => 'Face-to-face',
    ];

    public function intern()
    {
        return $this->belongsTo(User::class, 'intern_id');
    }

    public function office()
    {
        return $this->belongsTo(Office::class);
    }

    public function recipient()
    {
        return $this->belongsTo(User::class, 'recipient_id');
    }

    public function decider()
    {
        return $this->belongsTo(User::class, 'decided_by');
    }

    public function typeLabel(): string
    {
        return self::TYPE_LABELS[$this->type] ?? $this->type;
    }

    public function auditLabel(): string
    {
        return $this->intern?->full_name.' — '.$this->typeLabel();
    }
}
