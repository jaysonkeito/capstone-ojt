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
        // The "In case of an emergency, please notify" block of the school's
        // Personal Information sheet. Falls back to the guardian/parents
        // fields when left empty (see RequirementDocumentService::profile).
        Schema::table('intern_personal_infos', function (Blueprint $table) {
            $table->string('emergency_name')->nullable();
            $table->string('emergency_relationship', 60)->nullable();
            $table->string('emergency_address')->nullable();
            $table->string('emergency_contact', 60)->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('intern_personal_infos', function (Blueprint $table) {
            $table->dropColumn(['emergency_name', 'emergency_relationship', 'emergency_address', 'emergency_contact']);
        });
    }
};
