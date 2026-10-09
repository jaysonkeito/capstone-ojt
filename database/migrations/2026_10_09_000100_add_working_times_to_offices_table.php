<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Per-office working times. Null columns mean "use the campus-wide
     * standard from Settings" — an office only overrides the slots it
     * actually runs differently.
     */
    public function up(): void
    {
        Schema::table('offices', function (Blueprint $table) {
            $table->time('am_time_in')->nullable()->after('is_active');
            $table->time('am_time_out')->nullable()->after('am_time_in');
            $table->time('pm_time_in')->nullable()->after('am_time_out');
            $table->time('pm_time_out')->nullable()->after('pm_time_in');
        });
    }

    public function down(): void
    {
        Schema::table('offices', function (Blueprint $table) {
            $table->dropColumn(['am_time_in', 'am_time_out', 'pm_time_in', 'pm_time_out']);
        });
    }
};
