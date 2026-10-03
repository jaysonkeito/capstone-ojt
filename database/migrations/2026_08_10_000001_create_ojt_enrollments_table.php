<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * An intern can go through more than one OJT "set" over their time in the
 * system — e.g. a 300-hour Summer OJT followed later by a separate
 * 500-hour Internship OJT. Each set:
 *   - has its own custom target hours (not a fixed lookup table anymore),
 *   - accumulates hours from zero (via `ojt_logs.ojt_enrollment_id` —
 *     see the next migration), independent of any earlier set,
 *   - gets its own office/company placement and coordinator, reset to
 *     unassigned when the set starts (an intern may land at a completely
 *     different company for their next set),
 *   - is only ever created once the intern's previous set (if any) is
 *     marked `completed` — enforced in App\Support\OjtEnrollmentService,
 *     not the database, so only one set is ever open at a time.
 *
 * `users.ojt_track`, `target_hours`, `office_id`, `coordinator_id`, and
 * `ojt_status` are kept in sync as a "current set" mirror on the intern
 * row itself (updated by OjtEnrollmentService) so every existing query,
 * scope, and policy that filters/reads those columns keeps working
 * unchanged — `ojt_enrollments` is the durable history underneath it.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ojt_enrollments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            // Free-form label so this isn't locked to just Summer/Internship
            // — e.g. "Summer OJT", "Internship OJT", or a custom name.
            $table->string('label');
            $table->unsignedInteger('target_hours');
            $table->foreignId('office_id')->nullable()->constrained('offices')->nullOnDelete();
            $table->foreignId('coordinator_id')->nullable()->constrained('users')->nullOnDelete();
            $table->enum('status', ['pending', 'active', 'completed'])->default('pending');
            $table->date('started_at')->nullable();
            $table->date('completed_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ojt_enrollments');
    }
};
