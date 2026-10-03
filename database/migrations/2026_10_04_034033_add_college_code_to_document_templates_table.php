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
        // Which college an uploaded design belongs to — each college keeps
        // its own design per form type.
        Schema::table('document_templates', function (Blueprint $table) {
            $table->string('college_code', 20)->default('cas')->after('type');
            $table->unique(['type', 'college_code']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('document_templates', function (Blueprint $table) {
            $table->dropUnique(['type', 'college_code']);
            $table->dropColumn('college_code');
        });
    }
};
