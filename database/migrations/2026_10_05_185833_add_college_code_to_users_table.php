<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
    use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // The college a staff member belongs to — set at registration or by
        // the admin, and the source of truth for approvals scoping and
        // template-college access. Interns keep their department instead.
        Schema::table('users', function (Blueprint $table) {
            $table->string('college_code', 20)->nullable()->after('role');
        });

        // Existing staff ride the installation's default college.
        DB::table('users')
            ->whereIn('role', ['admin', 'coordinator', 'supervisor'])
            ->update(['college_code' => 'cas']);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('college_code');
        });
    }
};
