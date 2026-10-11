<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * A staff member's formal report against an intern — the paper trail for
 * suspected proxy scans ("it wasn't them"), fabricated entries, or duty
 * conduct. The System Admin adjudicates from the notification.
 */
class InternReport extends Model
{
    protected $fillable = [
        'intern_id',
        'reporter_id',
        'reason',
    ];

    public function intern()
    {
        return $this->belongsTo(User::class, 'intern_id');
    }

    public function reporter()
    {
        return $this->belongsTo(User::class, 'reporter_id');
    }

    public function auditLabel(): string
    {
        return $this->intern?->full_name.' — reported';
    }
}
