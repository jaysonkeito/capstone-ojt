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
        // The campus identity printed on certification letterheads and as the
        // establishment line — distinct from the college, which sits within
        // the campus.
        Schema::table('ojt_settings', function (Blueprint $table) {
            $table->string('campus_name')->default('Negros Oriental State University – Bayawan-Sta. Catalina Campus');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('ojt_settings', function (Blueprint $table) {
            $table->dropColumn('campus_name');
        });
    }
};
