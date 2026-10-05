<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * One line of the System Admin's activity log: who changed what, when, and
 * (for updates) exactly which fields moved from which value to which.
 * The user columns are snapshots — the trail survives account deletions.
 */
class AuditLog extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'user_id',
        'user_name',
        'user_role',
        'action',
        'subject_type',
        'subject_id',
        'subject_label',
        'changes',
        'created_at',
    ];

    protected $casts = [
        'changes' => 'array',
        'created_at' => 'datetime',
    ];

    /**
     * Fields never written to the trail, whatever the model carries.
     */
    public const REDACTED = ['password', 'remember_token', 'qr_token', 'scan_token'];

    /**
     * Fields that only add noise to a change diff.
     */
    public const IGNORED = ['created_at', 'updated_at', 'registered_at', 'qr_rotated_at'];

    /**
     * Record one trail entry. Resolves the acting user from the current
     * session (null for system/queued work — recorded as "System").
     */
    public static function record(string $action, Model $subject, array $changes = [], ?User $user = null): ?self
    {
        $user ??= auth()->user();

        return static::create([
            'user_id' => $user?->id,
            'user_name' => $user?->full_name ?? 'System',
            'user_role' => $user?->role ?? 'system',
            'action' => $action,
            'subject_type' => class_basename($subject),
            'subject_id' => $subject->id ?? null,
            'subject_label' => method_exists($subject, 'auditLabel') ? $subject->auditLabel() : null,
            'changes' => $changes ?: null,
            'created_at' => now(),
        ]);
    }
}
