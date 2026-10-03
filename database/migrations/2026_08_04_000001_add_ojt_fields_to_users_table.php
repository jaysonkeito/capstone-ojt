<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * This migration extends Laravel's default `users` table with the fields
 * required by the OJT Management System. If you are starting from a fresh
 * Laravel install, run this AFTER the default `0001_01_01_000000_create_users_table`
 * migration (the timestamp prefix already guarantees that ordering).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->enum('role', ['admin', 'intern'])->default('intern')->after('id');
            $table->string('student_id')->nullable()->unique()->after('role');
            $table->string('first_name')->nullable()->after('student_id');
            $table->string('last_name')->nullable()->after('first_name');
            $table->unsignedInteger('target_hours')->default(600)->after('last_name');
            $table->boolean('is_active')->default(true)->after('target_hours');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn([
                'role',
                'student_id',
                'first_name',
                'last_name',
                'target_hours',
                'is_active',
            ]);
        });
    }
};
