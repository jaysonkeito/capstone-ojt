<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Simplifies the daily time-log format down to just AM IN/OUT + PM IN/OUT
 * (matching a standard physical DTR). The separate OT IN/OUT clock session
 * is removed — all overtime is now derived automatically by comparing the
 * AM/PM times against the office's standard working-hours window (see
 * App\Support\HoursCalculator and the new `offices.standard_*` columns).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('ojt_logs', function (Blueprint $table) {
            $table->dropColumn(['ot_time_in', 'ot_time_out']);
        });
    }

    public function down(): void
    {
        Schema::table('ojt_logs', function (Blueprint $table) {
            $table->time('ot_time_in')->nullable()->after('pm_time_out');
            $table->time('ot_time_out')->nullable()->after('ot_time_in');
        });
    }
};
