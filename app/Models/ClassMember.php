<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * One member's preferences on a class board — membership is derived from
 * the coordinator assignment, this row only persists the overrides:
 * mute (no message notifications) and left (hidden from the class until
 * the coordinator adds them back). Rows appear on first use.
 */
class ClassMember extends Model
{
    protected $fillable = [
        'coordinator_id',
        'user_id',
        'name',
        'muted_at',
        'left_at',
    ];

    protected function casts(): array
    {
        return [
            'muted_at' => 'datetime',
            'left_at' => 'datetime',
        ];
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    /**
     * The member row for a user, creating it on first use.
     */
    public static function for(int $coordinatorId, int $userId): self
    {
        return static::firstOrCreate([
            'coordinator_id' => $coordinatorId,
            'user_id' => $userId,
        ]);
    }

    /**
     * The class chat's customized name — the coordinator sets it on their
     * own row ("Customize chat"); null until they do.
     */
    public static function chatNameFor(int $coordinatorId): ?string
    {
        return static::where('coordinator_id', $coordinatorId)
            ->where('user_id', $coordinatorId)
            ->value('name');
    }
}
