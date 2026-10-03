<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Adds an explicit OT IN / OT OUT clock session, matching the logbook
 * layout. This is *in addition to* — not a replacement for — the automatic
 * overtime detection on a late PM Time Out. An intern can either:
 *   (a) simply clock PM OUT late and have OT auto-computed, or
 *   (b) clock a separate OT IN/OUT session (e.g. a distinct overtime shift)
 * Both contribute to `overtime_hours`, computed automatically either way.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('ojt_logs', function (Blueprint $table) {
            $table->time('ot_time_in')->nullable()->after('pm_time_out');
            $table->time('ot_time_out')->nullable()->after('ot_time_in');
        });
    }

    public function down(): void
    {
        Schema::table('ojt_logs', function (Blueprint $table) {
            $table->dropColumn(['ot_time_in', 'ot_time_out']);
        });
    }
};
