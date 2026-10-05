<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('audit_logs', function (Blueprint $table) {
            $table->id();
            // Who did it — snapshot columns survive the user's deletion.
            $table->foreignId('user_id')->nullable()->index();
            $table->string('user_name')->nullable();
            $table->string('user_role', 30)->nullable();
            // What happened.
            $table->string('action', 20); // created | updated | deleted | restored | logged-in | logged-out
            $table->string('subject_type', 60);
            $table->unsignedBigInteger('subject_id')->nullable();
            $table->string('subject_label')->nullable();
            // Field-level diff for updates: { field: { old, new } }.
            $table->json('changes')->nullable();
            $table->timestamp('created_at')->index();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('audit_logs');
    }
};
