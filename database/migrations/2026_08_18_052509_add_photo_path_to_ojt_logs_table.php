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
            // The intern's single proof photo for the day, stored on the
            // public disk (path relative to storage/app/public). Null until
            // the intern uploads it after their clock-out.
            $table->string('photo_path')->nullable()->after('notes');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('ojt_logs', function (Blueprint $table) {
            $table->dropColumn('photo_path');
        });
    }
};
