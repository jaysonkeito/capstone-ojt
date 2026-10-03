<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Supports the "change your password on first login" policy: an intern
 * whose `password_changed_at` is still null (every seeded account, and
 * anyone the admin reset back to their last name) is prompted to pick
 * their own password before they can use the dashboard.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->timestamp('password_changed_at')->nullable()->after('password');
        });

        // Admins are never prompted (the middleware only targets interns),
        // but mark them so the column tells a complete story.
        DB::table('users')->where('role', 'admin')->update(['password_changed_at' => now()]);
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('password_changed_at');
        });
    }
};
