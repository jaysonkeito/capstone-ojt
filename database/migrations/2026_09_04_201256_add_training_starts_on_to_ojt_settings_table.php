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
        Schema::table('ojt_settings', function (Blueprint $table) {
            $table->date('training_starts_on')->nullable()->after('absence_start_date');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('ojt_settings', function (Blueprint $table) {
            $table->dropColumn('training_starts_on');
        });
    }
};
