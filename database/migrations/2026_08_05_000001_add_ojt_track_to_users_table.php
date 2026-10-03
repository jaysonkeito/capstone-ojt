<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Two OJT tracks with fixed target hours:
 *   - Summer OJT      => 300 hours (default for all existing interns)
 *   - Internship OJT  => 500 hours
 *
 * `target_hours` remains the source of truth for progress calculations
 * (App\Models\User accessors already sum against it) — this migration adds
 * the `ojt_track` label and backfills `target_hours` to match the default
 * track for every existing intern.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('ojt_track', 20)->default('summer')->after('coordinator_id');
        });

        // Backfill: every existing intern is on the Summer track (300h) by default.
        DB::table('users')
            ->where('role', 'intern')
            ->update(['ojt_track' => 'summer', 'target_hours' => 300]);
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('ojt_track');
        });
    }
};
