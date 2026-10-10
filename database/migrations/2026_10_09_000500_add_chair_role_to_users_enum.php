<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * The "chair" role: a Program Chair of a college. CAS, for example,
     * has two programs and therefore two Program Chairs. A chair belongs
     * to their college (staff_profiles.college_code) and monitors the
     * interns whose coordinators sit in that college — read-only
     * oversight, no office, no station.
     */
    public function up(): void
    {
        DB::statement("ALTER TABLE users MODIFY role ENUM('admin','intern','coordinator','supervisor','dean','office','chair') NOT NULL DEFAULT 'intern'");
    }

    public function down(): void
    {
        DB::table('users')->where('role', 'chair')->delete();
        DB::statement("ALTER TABLE users MODIFY role ENUM('admin','intern','coordinator','supervisor','dean','office') NOT NULL DEFAULT 'intern'");
    }
};
