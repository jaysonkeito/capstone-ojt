<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class OjtSetting extends Model
{
    protected $fillable = [
        'am_time_in',
        'am_time_out',
        'pm_time_in',
        'pm_time_out',
        'grace_period_minutes',
        'absence_start_date',
        'training_starts_on',
        'working_days',
        'qr_token',
        'qr_rotated_at',
        'geofence_enabled',
        'geofence_latitude',
        'geofence_longitude',
        'geofence_radius_meters',
        'college_name',
        'college_dean',
        'college_code',
        'campus_name',
    ];

    protected function casts(): array
    {
        return [
            'absence_start_date' => 'date',
            'training_starts_on' => 'date',
            'working_days' => 'array',
            'grace_period_minutes' => 'integer',
            'qr_rotated_at' => 'datetime',
            'geofence_enabled' => 'boolean',
            'geofence_latitude' => 'float',
            'geofence_longitude' => 'float',
            'geofence_radius_meters' => 'integer',
        ];
    }

    /**
     * There is only ever one settings row. Fetch it (creating sensible
     * defaults on first use) rather than querying by id everywhere.
     */
    public static function current(): self
    {
        return static::query()->firstOrCreate([], [
            'am_time_in' => '08:00:00',
            'am_time_out' => '12:00:00',
            'pm_time_in' => '13:00:00',
            'pm_time_out' => '17:00:00',
            'grace_period_minutes' => 15,
            'working_days' => [1, 2, 3, 4, 5], // Mon-Fri
            'geofence_enabled' => false,
            'geofence_radius_meters' => 200,
        ]);
    }

    /**
     * @deprecated The phone-scan wall poster (/scan/{token}) was removed in
     * favour of the office desk kiosk, which reads each intern's personal QR
     * (see User::scanQrPayload()). Retained only because the qr_token /
     * geofence columns are still on the table; safe to drop in a future
     * migration along with the geofence helpers below.
     *
     * The token that used to be embedded in the printed QR poster, generated
     * on first use.
     */
    public static function currentQrToken(): string
    {
        $settings = static::current();

        if (! $settings->qr_token) {
            $settings->refreshQrToken();
        }

        return $settings->qr_token;
    }

    /**
     * Replace the QR token. Every previously printed poster stops working
     * immediately — the mitigation when a poster is photographed or shared
     * outside the office.
     */
    public function refreshQrToken(): string
    {
        $this->forceFill([
            'qr_token' => Str::random(40),
            'qr_rotated_at' => now(),
        ])->save();

        return $this->qr_token;
    }

    /**
     * Whether the location check is switched on AND an office pin has been
     * dropped. When either is missing, geofencing is effectively off — the
     * scan flow treats every location as acceptable (fail-open), so a
     * half-configured fence can never lock interns out of timing in.
     */
    public function geofenceActive(): bool
    {
        return $this->geofence_enabled
            && $this->geofence_latitude !== null
            && $this->geofence_longitude !== null;
    }

    /**
     * Is a scan reported from the given coordinates close enough to the
     * office pin to count? True whenever geofencing isn't active (fail-open).
     */
    public function isWithinGeofence(float $latitude, float $longitude): bool
    {
        if (! $this->geofenceActive()) {
            return true;
        }

        return $this->distanceMetersTo($latitude, $longitude) <= $this->geofence_radius_meters;
    }

    /**
     * Great-circle (haversine) distance in metres from the configured office
     * pin to the given point.
     */
    public function distanceMetersTo(float $latitude, float $longitude): float
    {
        $earthRadiusMeters = 6_371_000;

        $latFrom = deg2rad((float) $this->geofence_latitude);
        $lngFrom = deg2rad((float) $this->geofence_longitude);
        $latTo = deg2rad($latitude);
        $lngTo = deg2rad($longitude);

        $latDelta = $latTo - $latFrom;
        $lngDelta = $lngTo - $lngFrom;

        $a = sin($latDelta / 2) ** 2
            + cos($latFrom) * cos($latTo) * sin($lngDelta / 2) ** 2;

        return $earthRadiusMeters * 2 * asin(min(1.0, sqrt($a)));
    }

    public function isWorkingDay(\DateTimeInterface $date): bool
    {
        // ISO-8601 weekday: 1 (Monday) through 7 (Sunday). We store Sun=0..Sat=6
        // to match Carbon's dayOfWeek for simplicity in the UI toggles.
        return in_array((int) $date->format('w'), $this->working_days ?? [], true);
    }
}
