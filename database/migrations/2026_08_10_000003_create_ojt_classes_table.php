<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * A "class" a Coordinator creates once — e.g. "Summer OJT 2026" (300h) —
 * that many interns then join themselves using a 6-character code,
 * similar to a Google-Classroom-style join flow. Each intern's actual
 * participation (their own office pick, their own hours, pending/active/
 * completed status) lives in `ojt_enrollments.ojt_class_id` — this table
 * is just the shared shell: label, target hours, and the code.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ojt_classes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('coordinator_id')->constrained('users')->cascadeOnDelete();
            $table->string('label');
            $table->unsignedInteger('target_hours');
            $table->string('join_code', 6)->unique();
            // Coordinator can stop accepting new join requests without
            // affecting interns who already joined.
            $table->boolean('is_open')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ojt_classes');
    }
};
