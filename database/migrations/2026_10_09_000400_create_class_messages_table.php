<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * The class board: one discussion per coordinator and their assigned
     * interns. Every member sees every message; any member can post.
     */
    public function up(): void
    {
        Schema::create('class_messages', function (Blueprint $table) {
            $table->id();
            // The class this message belongs to — keyed on the coordinator.
            $table->foreignId('coordinator_id')->constrained('users')->cascadeOnDelete();
            // Who wrote it — the coordinator or one of their interns.
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->text('body');
            $table->timestamps();

            $table->index(['coordinator_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('class_messages');
    }
};
