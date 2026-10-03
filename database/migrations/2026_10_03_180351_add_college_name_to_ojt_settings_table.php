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
        // The College whose letterheads and agreements the forms carry —
        // merged as ${college_name} so other colleges can adopt the system
        // without editing templates.
        Schema::table('ojt_settings', function (Blueprint $table) {
            $table->string('college_name')->default('College of Arts and Sciences');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('ojt_settings', function (Blueprint $table) {
            $table->dropColumn('college_name');
        });
    }
};
