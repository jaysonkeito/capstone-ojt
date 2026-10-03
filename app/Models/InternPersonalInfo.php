<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * The intern-entered personal details behind the school's "Student Intern's
 * Personal Information" requirement form — birth details, addresses, contact
 * numbers, and family background the core users table never stored. One row
 * per intern; every field is optional and fills that form's ${tokens} at
 * download time (anything still blank prints as "N/A").
 */
class InternPersonalInfo extends Model
{
    use HasFactory;

    /**
     * Pluralizing "info" is ambiguous, so pin the table name explicitly.
     */
    protected $table = 'intern_personal_infos';

    protected $fillable = [
        'user_id',
        'middle_name',
        'birthdate',
        'sex',
        'height',
        'weight',
        'complexion',
        'disability',
        'birth_place',
        'citizenship',
        'civil_status',
        'present_address',
        'present_contact',
        'permanent_address',
        'permanent_contact',
        'father_name',
        'father_occupation',
        'mother_name',
        'mother_occupation',
        'parents_address',
        'parents_contact',
        'guardian_name',
        'guardian_contact',
        'emergency_name',
        'emergency_relationship',
        'emergency_address',
        'emergency_contact',
        'institutional_email',
        'phone_number',
        'major',
        'facebook_link',
        'youtube_link',
        'linkedin_link',
    ];

    protected function casts(): array
    {
        return [
            'birthdate' => 'date',
        ];
    }

    /**
     * The intern these details belong to.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
