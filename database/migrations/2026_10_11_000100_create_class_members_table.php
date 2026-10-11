<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Per-member chat preferences on a class board. Membership itself is
     * derived (the coordinator plus interns assigned to them) — rows here
     * are created on demand to persist the overrides: muted_at silences
     * message notifications, left_at removes someone from the class until
     * the coordinator adds them back.
     */
    public function up(): void
    {
        Schema::create('class_members', function (Blueprint $table) {
            $table->id();
            $table->foreignId('coordinator_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            // The coordinator's customized chat name ("Customize chat") —
            // stored on the coordinator's own row and shown to everyone.
            $table->string('name')->nullable();
            $table->timestamp('muted_at')->nullable();
            $table->timestamp('left_at')->nullable();
            $table->timestamps();

            $table->unique(['coordinator_id', 'user_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('class_members');
    }
};
