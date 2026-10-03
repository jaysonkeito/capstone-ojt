<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Completion recommendations — an OJT coordinator formally recommends an
 * intern whose hours reached the target for completion of the OJT set.
 * The System Admin's approval closes the set through
 * OjtEnrollmentService (the single place sets are closed), stamps the
 * intern completed, and notifies the coordinator.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('completion_recommendations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('intern_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('coordinator_id')->constrained('users')->cascadeOnDelete();
            $table->text('note')->nullable();
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
        Schema::dropIfExists('completion_recommendations');
    }
};
