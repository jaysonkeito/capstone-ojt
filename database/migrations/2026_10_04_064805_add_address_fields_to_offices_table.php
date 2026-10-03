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
        // Structured letterhead address — the Certification header (and any
        // future form) composes these per host office instead of hardcoding
        // one office's address in the template.
        Schema::table('offices', function (Blueprint $table) {
            $table->string('agency')->nullable();
            $table->string('city')->nullable();
            $table->string('province')->nullable();
            $table->string('postal', 20)->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('offices', function (Blueprint $table) {
            $table->dropColumn(['agency', 'city', 'province', 'postal']);
        });
    }
};
