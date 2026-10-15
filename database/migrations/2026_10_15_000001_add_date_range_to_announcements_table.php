<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Announcements can now carry a display window: the dashboard banner
 * appears on starts_at and disappears on ends_at without anyone having
 * to delete the post. Null boundaries stay open-ended — existing rows
 * keep showing exactly as before (starts_at backfilled to their publish
 * date).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('announcements', function (Blueprint $table) {
            $table->date('starts_at')->nullable()->after('body');
            $table->date('ends_at')->nullable()->after('starts_at');
        });

        DB::table('announcements')
            ->whereNull('starts_at')
            ->update(['starts_at' => DB::raw('DATE(created_at)')]);
    }

    public function down(): void
    {
        Schema::table('announcements', function (Blueprint $table) {
            $table->dropColumn(['starts_at', 'ends_at']);
        });
    }
};
