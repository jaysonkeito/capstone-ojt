<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Intern attendance requests — a correction (wrong/missed scan times on
 * an existing entry; carries the proposed times) or an absence report (a
 * duty day with no entry; informational, no log is ever created). The
 * office supervisor or the intern's coordinator decides; an approved
 * correction is applied to the log by App\Support\LogRequestService
 * (hours recompute through the model's saving hook).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('log_requests', function (Blueprint $table) {
            $table->id();
            $table->foreignId('intern_id')->constrained('users')->cascadeOnDelete();
            // The entry a correction targets; null for an absence report.
            $table->foreignId('ojt_log_id')->nullable()->constrained('ojt_logs')->cascadeOnDelete();
            $table->enum('type', ['correction', 'absence'])->default('correction');
            $table->date('date');
            $table->time('am_time_in')->nullable();
            $table->time('am_time_out')->nullable();
            $table->time('am_time_in_2')->nullable();
            $table->time('am_time_out_2')->nullable();
            $table->time('pm_time_in')->nullable();
            $table->time('pm_time_out')->nullable();
            $table->time('pm_time_in_2')->nullable();
            $table->time('pm_time_out_2')->nullable();
            $table->text('reason');
            $table->enum('status', ['pending', 'approved', 'rejected'])->default('pending');
            $table->foreignId('decided_by')->nullable()->constrained('users')->nullOnDelete();
            $table->text('decision_comment')->nullable();
            $table->timestamp('decided_at')->nullable();
            $table->timestamps();

            $table->index(['status', 'created_at']);
            $table->index(['intern_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('log_requests');
    }
};
