<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Location check (geofencing) for the QR time in/out.
 *
 * A printed poster is a bearer credential — a photo of it works from
 * anywhere. Geofencing keeps the printout but has the intern's browser
 * report its coordinates on scan, and rejects any scan taken outside a
 * radius of the office pin. Configured site-wide (there is a single
 * campus poster), so these columns live on the one ojt_settings row.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('ojt_settings', function (Blueprint $table) {
            $table->boolean('geofence_enabled')->default(false)->after('qr_rotated_at');
            // Nullable until an admin drops the office pin. decimal(10,7) holds
            // the full ±90 / ±180 range at ~1cm precision (far finer than GPS).
            $table->decimal('geofence_latitude', 10, 7)->nullable()->after('geofence_enabled');
            $table->decimal('geofence_longitude', 10, 7)->nullable()->after('geofence_latitude');
            // How far from the pin still counts as "at the office". Default 200m
            // absorbs typical phone-GPS drift, especially indoors.
            $table->unsignedSmallInteger('geofence_radius_meters')->default(200)->after('geofence_longitude');
        });
    }

    public function down(): void
    {
        Schema::table('ojt_settings', function (Blueprint $table) {
            $table->dropColumn([
                'geofence_enabled',
                'geofence_latitude',
                'geofence_longitude',
                'geofence_radius_meters',
            ]);
        });
    }
};
