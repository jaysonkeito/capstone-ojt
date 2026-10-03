<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Admin approval for self-service staff (coordinator / supervisor) sign-ups.
 *
 * A self-registered staff account starts inactive with `approved_at` NULL and
 * waits in the admin Approvals section. Approving it activates the account and
 * stamps `approved_at`; rejecting it removes the request entirely. Interns and
 * admin-provisioned staff are approved by construction (`approved_at` set the
 * moment they are created), so `approved_at IS NULL` alone never means
 * "waiting" for them.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->timestamp('approved_at')->nullable()->after('is_active');
        });

        // Everyone who exists today predates the approval gate, so every
        // current account is treated as already approved.
        DB::table('users')->update(['approved_at' => now()]);
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('approved_at');
        });
    }
};
