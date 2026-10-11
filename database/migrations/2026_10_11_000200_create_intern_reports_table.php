<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * A staff member's formal report against an intern — the paper trail
     * for "someone else scanned their QR", fabricated entries, conduct
     * during duty, and the like. Filed by the System Admin, a coordinator,
     * a supervisor, or a Program Chair; lands in the System Admin's inbox.
     */
    public function up(): void
    {
        Schema::create('intern_reports', function (Blueprint $table) {
            $table->id();
            $table->foreignId('intern_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('reporter_id')->constrained('users')->cascadeOnDelete();
            $table->text('reason');
            $table->timestamps();

            $table->index(['intern_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('intern_reports');
    }
};
