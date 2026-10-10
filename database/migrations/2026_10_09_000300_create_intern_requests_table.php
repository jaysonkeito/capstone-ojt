<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Intern-initiated requests to their coordinator: applying to another
     * office (office_transfer) or asking for a consultation (online or
     * face-to-face). The coordinator decides with remarks; an approved
     * office transfer moves the intern's placement on the spot.
     */
    public function up(): void
    {
        Schema::create('intern_requests', function (Blueprint $table) {
            $table->id();
            $table->foreignId('intern_id')->constrained('users')->cascadeOnDelete();
            $table->enum('type', ['office_transfer', 'consultation']);
            // office_transfer: the office being applied to.
            $table->foreignId('office_id')->nullable()->constrained()->nullOnDelete();
            // consultation: how to meet, and optionally a supervisor instead
            // of the coordinator. Null recipient = the intern's coordinator.
            $table->enum('mode', ['online', 'f2f'])->nullable();
            $table->foreignId('recipient_id')->nullable()->constrained('users')->nullOnDelete();
            $table->text('message');
            $table->enum('status', ['pending', 'approved', 'rejected'])->default('pending');
            $table->text('decision_remarks')->nullable();
            $table->foreignId('decided_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('decided_at')->nullable();
            $table->timestamps();

            $table->index(['status', 'recipient_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('intern_requests');
    }
};
