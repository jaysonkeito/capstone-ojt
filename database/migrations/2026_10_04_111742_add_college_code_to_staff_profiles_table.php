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
        // The college a staff member belongs to — scopes coordinators to
        // their college's document templates. Existing staff default to the
        // installation's founding college (CAS); the Staff form sets it for
        // new hires.
        Schema::table('staff_profiles', function (Blueprint $table) {
            $table->string('college_code', 20)->nullable()->after('user_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('staff_profiles', function (Blueprint $table) {
            $table->dropColumn('college_code');
        });
    }
};
