<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Timesheet certification — the office supervisor's formal per-period
 * sign-off on an intern's duty hours. One row per intern per period
 * (month); created at the moment of certification. The record backs the
 * "Certified" state in the monitor UI and the certified_on merge key in
 * the Word timesheet.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('timesheet_certifications', function (Blueprint $table) {
            $table->id();
            $table->foreignId('intern_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('supervisor_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('ojt_enrollment_id')->nullable()->constrained('ojt_enrollments')->nullOnDelete();
            $table->date('period_start');
            $table->date('period_end');
            $table->timestamp('certified_at');
            $table->timestamps();

            // A period can only be certified once per intern.
            $table->unique(['intern_id', 'period_start']);
            $table->index(['intern_id', 'period_start', 'period_end']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('timesheet_certifications');
    }
};
