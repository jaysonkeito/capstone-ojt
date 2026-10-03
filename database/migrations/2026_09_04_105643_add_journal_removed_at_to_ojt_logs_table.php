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
        Schema::table('ojt_logs', function (Blueprint $table) {
            // When the intern "removes" their journal, the proof photo and
            // notes stay in place and only this timestamp is stamped — a
            // soft delete so the removal can be undone. Null while the
            // journal is live.
            $table->timestamp('journal_removed_at')->nullable()->after('photo_path');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('ojt_logs', function (Blueprint $table) {
            $table->dropColumn('journal_removed_at');
        });
    }
};