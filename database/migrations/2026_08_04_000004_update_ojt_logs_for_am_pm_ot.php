<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Replaces the manual "hours_rendered" number entry with an AM IN/OUT,
 * PM IN/OUT logbook style entry (matching a physical DTR). Regular hours
 * and overtime are computed automatically from these times against the
 * standard working hours in `ojt_settings` — see App\Support\HoursCalculator.
 *
 * `hours_rendered` is kept as a stored, auto-computed total
 * (regular_hours + overtime_hours) so existing sum() queries keep working.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('ojt_logs', function (Blueprint $table) {
            $table->time('am_time_in')->nullable()->after('date');
            $table->time('am_time_out')->nullable()->after('am_time_in');
            $table->time('pm_time_in')->nullable()->after('am_time_out');
            $table->time('pm_time_out')->nullable()->after('pm_time_in');
            $table->decimal('regular_hours', 5, 2)->default(0)->after('pm_time_out');
            $table->decimal('overtime_hours', 5, 2)->default(0)->after('regular_hours');
            $table->text('notes')->nullable()->after('overtime_hours');
        });

        // activity_description is superseded by `notes` above.
        if (Schema::hasColumn('ojt_logs', 'activity_description')) {
            Schema::table('ojt_logs', function (Blueprint $table) {
                $table->dropColumn('activity_description');
            });
        }
    }

    public function down(): void
    {
        Schema::table('ojt_logs', function (Blueprint $table) {
            $table->text('activity_description')->nullable();
            $table->dropColumn([
                'am_time_in',
                'am_time_out',
                'pm_time_in',
                'pm_time_out',
                'regular_hours',
                'overtime_hours',
                'notes',
            ]);
        });
    }
};
