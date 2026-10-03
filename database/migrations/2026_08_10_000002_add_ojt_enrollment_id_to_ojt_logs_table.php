<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Every daily log belongs to a specific OJT set (`ojt_enrollments`), so
 * hours accumulate per-set instead of for the intern's whole lifetime —
 * a fresh 500-hour Internship set starts its hour count at zero even
 * though the same intern already logged 300 hours on an earlier Summer set.
 *
 * Backfill strategy for data that predates this feature: every existing
 * intern gets exactly one `ojt_enrollments` row built from their current
 * `ojt_track` / `target_hours` / `office_id` / `coordinator_id` /
 * `ojt_status`, and every one of their existing `ojt_logs` rows is pointed
 * at that single backfilled set. Nothing is lost or renumbered — this is
 * simply naming the set that was already implicitly the only one.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('ojt_logs', function (Blueprint $table) {
            $table->foreignId('ojt_enrollment_id')->nullable()->after('user_id')
                ->constrained('ojt_enrollments')->cascadeOnDelete();
        });

        $interns = DB::table('users')->where('role', 'intern')->get();

        foreach ($interns as $intern) {
            $label = match ($intern->ojt_track) {
                'summer' => 'Summer OJT',
                'internship' => 'Internship OJT',
                default => $intern->ojt_track ? ucfirst($intern->ojt_track) : 'OJT',
            };

            $enrollmentId = DB::table('ojt_enrollments')->insertGetId([
                'user_id' => $intern->id,
                'label' => $label,
                'target_hours' => $intern->target_hours ?: 0,
                'office_id' => $intern->office_id,
                'coordinator_id' => $intern->coordinator_id,
                'status' => in_array($intern->ojt_status, ['pending', 'active', 'completed'], true) ? $intern->ojt_status : 'pending',
                'started_at' => $intern->created_at,
                'completed_at' => $intern->ojt_status === 'completed' ? $intern->updated_at : null,
                'created_at' => $intern->created_at,
                'updated_at' => $intern->updated_at,
            ]);

            DB::table('ojt_logs')->where('user_id', $intern->id)->update([
                'ojt_enrollment_id' => $enrollmentId,
            ]);
        }

        // Every intern now has exactly one enrollment, and every one of
        // their logs points at it — safe to require it going forward.
        DB::statement('ALTER TABLE ojt_logs MODIFY ojt_enrollment_id BIGINT UNSIGNED NOT NULL');
    }

    public function down(): void
    {
        Schema::table('ojt_logs', function (Blueprint $table) {
            $table->dropForeign(['ojt_enrollment_id']);
            $table->dropColumn('ojt_enrollment_id');
        });
    }
};
