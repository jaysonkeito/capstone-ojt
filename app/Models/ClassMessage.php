<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * One message on a coordinator's class board — the shared channel where
 * a coordinator and their assigned interns talk. Membership is derived:
 * the coordinator and interns whose coordinator_id points at them.
 */
class ClassMessage extends Model
{
    protected $fillable = [
        'coordinator_id',
        'user_id',
        'body',
    ];

    public function coordinator()
    {
        return $this->belongsTo(User::class, 'coordinator_id');
    }

    public function author()
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function auditLabel(): string
    {
        return $this->coordinator?->full_name.' class board';
    }
}
