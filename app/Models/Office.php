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
        'am_time_in',
        'am_time_out',
        'pm_time_in',
        'pm_time_out',
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

    /**
     * Every account that supervises this office: its primary role OR any
     * extra role — a coordinator who also supervises the office counts.
     */
    public function supervisors()
    {
        return $this->hasMany(User::class)->where(function ($q) {
            $q->where('role', 'supervisor')
                ->orWhereHas('roles', fn ($r) => $r->where('role', 'supervisor'));
        });
    }

    public function getTypeLabelAttribute(): string
    {
        return $this->type === 'external' ? 'External' : 'Internal';
    }

    /**
     * The line the System Admin's activity log shows for this office.
     */
    public function auditLabel(): string
    {
        return $this->name;
    }
}
