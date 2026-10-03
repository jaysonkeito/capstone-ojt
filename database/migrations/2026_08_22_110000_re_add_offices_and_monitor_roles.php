<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Re-introduces offices and two read-only monitoring roles on top of the
 * QR system — Admin still manages everything, interns still time in by
 * scanning, and coordinators/supervisors get monitoring dashboards:
 *
 *  - `offices` table (internal/external) hosting interns and supervisors.
 *  - `coordinator` role: monitors the interns assigned to them, seeing
 *    each one's office and supervisor.
 *  - `supervisor` role: monitors the interns placed in their office,
 *    seeing rendered hours and each one's coordinator.
 *  - interns: `office_id` (placement) + `coordinator_id` (assignment).
 *
 * Also retires the Summer OJT track: every intern moves to the
 * Internship OJT track (500 target hours). Completed enrollments keep
 * their historical target; in-progress ones move up to 500.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::statement("ALTER TABLE users MODIFY role ENUM('admin','intern','coordinator','supervisor') NOT NULL DEFAULT 'intern'");

        Schema::create('offices', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->enum('type', ['internal', 'external'])->default('internal');
            $table->string('address')->nullable();
            $table->string('contact_person')->nullable();
            $table->string('contact_email')->nullable();
            $table->string('contact_phone')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::table('users', function (Blueprint $table) {
            $table->foreignId('office_id')->nullable()->after('password_changed_at')->constrained('offices')->nullOnDelete();
            $table->foreignId('coordinator_id')->nullable()->after('office_id')->constrained('users')->nullOnDelete();
        });

        // --- Summer OJT is retired: everyone moves to Internship OJT ----
        // (the old column default was 'summer' — covers every row, admin
        // accounts included)
        DB::table('users')
            ->where('ojt_track', 'summer')
            ->update(['ojt_track' => 'internship']);

        DB::table('users')
            ->where('role', 'intern')
            ->where('target_hours', 300)
            ->update(['target_hours' => 500]);

        // In-progress sets move up to the 500-hour standard; completed
        // ones keep the target they actually finished against.
        DB::table('ojt_enrollments')
            ->where('label', 'Summer OJT')
            ->where('status', '!=', 'completed')
            ->update(['label' => 'Internship OJT', 'target_hours' => 500]);

        DB::table('ojt_enrollments')
            ->where('label', 'Summer OJT')
            ->update(['label' => 'Internship OJT']);
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropConstrainedForeignId('office_id');
            $table->dropConstrainedForeignId('coordinator_id');
        });

        Schema::dropIfExists('offices');

        // Coordinator/supervisor accounts created after this migration
        // cannot be identified here — delete them manually before
        // rolling back.
        DB::statement("ALTER TABLE users MODIFY role ENUM('admin','intern') NOT NULL DEFAULT 'intern'");
    }
};
