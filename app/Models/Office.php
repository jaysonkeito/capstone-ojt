<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Office extends Model
{
    protected $fillable = [
        'name',
        'type',
        'address',
        'agency',
        'city',
        'province',
        'postal',
        'contact_person',
        'contact_email',
        'contact_phone',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
        ];
    }

    /**
     * Everyone placed at this office: interns and the supervisor
     * representing it.
     */
    public function users()
    {
        return $this->hasMany(User::class);
    }

    public function interns()
    {
        return $this->hasMany(User::class)->where('role', 'intern');
    }

    public function supervisors()
    {
        return $this->hasMany(User::class)->where('role', 'supervisor');
    }

    public function getTypeLabelAttribute(): string
    {
        return $this->type === 'external' ? 'External' : 'Internal';
    }
}
