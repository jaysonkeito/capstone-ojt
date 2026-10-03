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
        // The installation's college: its display name (merged as
        // ${college_name}), its dean (merged as ${college_dean}), and the
        // code that scopes which template folder serves its forms
        // (public/documents/templates/{college_code}/).
        Schema::table('ojt_settings', function (Blueprint $table) {
            $table->string('college_dean')->default('JEAN CARREM R. ESPARCIA, Ph.D.');
            $table->string('college_code', 20)->default('cas');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('ojt_settings', function (Blueprint $table) {
            $table->dropColumn(['college_dean', 'college_code']);
        });
    }
};
