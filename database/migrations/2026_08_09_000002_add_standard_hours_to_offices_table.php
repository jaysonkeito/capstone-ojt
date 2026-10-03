<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Lets each Office/Company define its own standard AM/PM working-hour
 * window, instead of relying solely on the single campus-wide `ojt_settings`
 * row. Any log time outside an office's window is auto-classified as
 * overtime — see App\Support\HoursCalculator.
 *
 * Nullable by design: an office that hasn't configured its own hours yet
 * falls back to the campus-wide `ojt_settings` defaults (see
 * App\Models\Office::standardHours()).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('offices', function (Blueprint $table) {
            $table->time('standard_am_start')->nullable()->after('campus_affiliation');
            $table->time('standard_am_end')->nullable()->after('standard_am_start');
            $table->time('standard_pm_start')->nullable()->after('standard_am_end');
            $table->time('standard_pm_end')->nullable()->after('standard_pm_start');
        });
    }

    public function down(): void
    {
        Schema::table('offices', function (Blueprint $table) {
            $table->dropColumn(['standard_am_start', 'standard_am_end', 'standard_pm_start', 'standard_pm_end']);
        });
    }
};
