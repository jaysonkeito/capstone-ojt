<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Placement requests — an OJT coordinator proposes an office for one of
 * their interns; the System Admin approves (the office is assigned) or
 * rejects (with a reason the coordinator sees). One pending request per
 * intern is enforced in code (App\Support\CoordinatorRequests); the
 * status index backs the admin's queue queries.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('placement_requests', function (Blueprint $table) {
            $table->id();
            $table->foreignId('intern_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('coordinator_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('office_id')->constrained('offices')->cascadeOnDelete();
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
        Schema::dropIfExists('placement_requests');
    }
};
