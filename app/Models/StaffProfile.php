<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * The instructor-facing details behind the non-student (coordinator /
 * supervisor) profile completion form — employee record, employment dates,
 * qualification, and resume the core users table never stored. One row per
 * staff account; every field is optional and the form can be completed at
 * the account's own pace.
 */
class StaffProfile extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'middle_name',
        'prefix_title',
        'suffix_title',
        'employee_id',
        'institutional_email',
        'civil_status',
        'designation',
        'date_hired',
        'department',
        'gender',
        'mobile_number',
        'qualification',
        'specialization',
        'resume_path',
    ];

    protected function casts(): array
    {
        return [
            'date_hired' => 'date',
        ];
    }

    /**
     * The staff account these details belong to.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
