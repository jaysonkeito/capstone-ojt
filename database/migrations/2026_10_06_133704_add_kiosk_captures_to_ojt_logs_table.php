<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Webcam snapshots the office kiosk takes of the intern at the moment
     * of each successful scan, so supervisors and coordinators can verify
     * the person behind each time entry. One JSON map keyed by the day's
     * slots ('am_time_in', 'pm_time_out', …) → storage path on the public
     * disk; a log row covers up to a full day of slots.
     */
    public function up(): void
    {
        Schema::table('ojt_logs', function (Blueprint $table) {
            $table->json('kiosk_captures')->nullable()->after('photo_path');
        });
    }

    public function down(): void
    {
        Schema::table('ojt_logs', function (Blueprint $table) {
            $table->dropColumn('kiosk_captures');
        });
    }
};
