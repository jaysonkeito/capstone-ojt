<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Adds fields needed for the cross-cutting intern filters:
 *  - ojt_status: Pending (awaiting supervisor approval) / Active / Completed
 *  - batch: free-text cohort label (e.g. "2026 Summer Batch A")
 *  - department: course/program (e.g. "BSCS", "BSINT")
 *
 * Existing interns are backfilled to 'active' since they're already
 * mid-placement, not awaiting approval.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->enum('ojt_status', ['pending', 'active', 'completed'])->default('pending')->after('ojt_track');
            $table->string('batch')->nullable()->after('ojt_status');
            $table->string('department', 20)->nullable()->after('batch');
        });

        DB::table('users')->where('role', 'intern')->update(['ojt_status' => 'active']);
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['ojt_status', 'batch', 'department']);
        });
    }
};
