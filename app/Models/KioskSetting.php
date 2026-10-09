<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Per-office kiosk station controls — which station tabs (Scanner,
 * Camera, Student ID) the office supervisor or System Admin has locked.
 * A null office_id row is the campus-wide default; an office's own row
 * overrides it for that office's stations.
 */
class KioskSetting extends Model
{
    protected $fillable = [
        'office_id',
        'lock_scanner',
        'lock_camera',
        'lock_manual',
        'updated_by',
    ];

    protected $casts = [
        'lock_scanner' => 'boolean',
        'lock_camera' => 'boolean',
        'lock_manual' => 'boolean',
    ];

    /**
     * The locks in effect for a station run by the given staff member:
     * their office's row when one exists, else the campus-wide default,
     * else everything unlocked.
     *
     * @return array{lock_scanner: bool, lock_camera: bool, lock_manual: bool}
     */
    public static function locksFor(?int $officeId): array
    {
        $row = $officeId
            ? static::where('office_id', $officeId)->first()
            : null;

        $row ??= static::whereNull('office_id')->first();

        return $row
            ? ['lock_scanner' => $row->lock_scanner, 'lock_camera' => $row->lock_camera, 'lock_manual' => $row->lock_manual]
            : ['lock_scanner' => false, 'lock_camera' => false, 'lock_manual' => false];
    }
}
