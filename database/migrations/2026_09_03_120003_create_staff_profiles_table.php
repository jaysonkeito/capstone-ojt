<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The instructor-facing details behind the non-student (coordinator /
 * supervisor) profile completion form — the employment record the core
 * users table never carried. One optional row per staff account; mirrors
 * how interns keep their personal details in `intern_personal_infos`.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('staff_profiles', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')
                ->unique()
                ->constrained('users')
                ->cascadeOnDelete();
            $table->string('middle_name')->nullable();
            $table->string('prefix_title', 50)->nullable();
            $table->string('suffix_title', 50)->nullable();
            $table->string('employee_id', 50)->nullable();
            $table->string('institutional_email')->nullable();
            $table->string('civil_status')->nullable();
            $table->string('designation')->nullable();
            $table->date('date_hired')->nullable();
            $table->string('department')->nullable();
            $table->string('gender')->nullable();
            $table->string('mobile_number', 30)->nullable();
            $table->string('qualification')->nullable();
            $table->string('specialization')->nullable();
            $table->string('resume_path')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('staff_profiles');
    }
};
