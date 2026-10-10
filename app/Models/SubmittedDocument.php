<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * One requirement document an intern submitted back for review — the
 * counterpart of the Requirements page's downloads. A type's LATEST
 * submission is the deciding one: approvals clear the requirement, and a
 * rejection stays until a newer file replaces it.
 */
class SubmittedDocument extends Model
{
    protected $fillable = [
        'user_id',
        'type',
        'file_path',
        'original_name',
        'status',
        'remarks',
        'reviewed_by',
        'reviewed_at',
    ];

    protected function casts(): array
    {
        return [
            'reviewed_at' => 'datetime',
        ];
    }

    public function intern()
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function reviewer()
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }

    /**
     * The document's display name, from the shared requirements catalog.
     */
    public function typeLabel(): string
    {
        return DocumentTemplate::TYPES[$this->type]['label'] ?? $this->type;
    }

    public function auditLabel(): string
    {
        return $this->intern?->full_name.' — '.$this->typeLabel();
    }
}
