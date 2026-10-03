<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Profile completion gate for self-service sign-ups.
 *
 * A fresh account (self-registered intern, or approved coordinator /
 * supervisor) is routed to the profile completion page on its first
 * sign-in and is kept away from the dashboards until the form has been
 * saved at least once. `profile_completed_at` records that save;
 * `username` is the handle the completion forms collect (per the profile
 * mockups). Accounts that existed before this gate are stamped completed
 * by construction — the gate only ever applies to accounts created after
 * this migration.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('username', 50)
                ->nullable()
                ->unique()
                ->after('email');
            $table->timestamp('profile_completed_at')
                ->nullable()
                ->after('approved_at');
        });

        // Everyone who exists today predates the completion gate, so every
        // current account is treated as already having completed its profile.
        DB::table('users')->update(['profile_completed_at' => now()]);
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropUnique(['username']);
            $table->dropColumn(['username', 'profile_completed_at']);
        });
    }
};
