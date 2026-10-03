<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * A half-day can hold a "stepped out / came back" pair alongside its main
     * session: an intern who times out early (say 10:30) and returns before
     * that session's time out (say 11:00) records 10:30 as AM Time Out and
     * 11:00 as "AM Time In (2)" — the gap columns — instead of the old
     * behavior of hijacking PM Time In and corrupting the afternoon. The
     * pair stays nullable: an ordinary day never touches it.
     */
    public function up(): void
    {
        Schema::table('ojt_logs', function (Blueprint $table) {
            $table->string('am_time_in_2')->nullable()->after('am_time_out');
            $table->string('am_time_out_2')->nullable()->after('am_time_in_2');
            $table->string('pm_time_in_2')->nullable()->after('pm_time_out');
            $table->string('pm_time_out_2')->nullable()->after('pm_time_in_2');
        });
    }

    public function down(): void
    {
        Schema::table('ojt_logs', function (Blueprint $table) {
            $table->dropColumn(['am_time_in_2', 'am_time_out_2', 'pm_time_in_2', 'pm_time_out_2']);
        });
    }
};
