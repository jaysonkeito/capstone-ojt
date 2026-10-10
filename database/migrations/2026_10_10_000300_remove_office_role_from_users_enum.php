<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * The office scanner role is retired: the desk station runs under the
     * supervisor's (or coordinator's/dean's/chair's) own account, and
     * leaving it is password-confirmed — the separate station account no
     * longer earns its keep. The station accounts carry nothing of their
     * own (no logs, no placements), so they delete cleanly; any station
     * tab-lock rows they last touched keep their other fields.
     */
    public function up(): void
    {
        DB::table('users')->where('role', 'office')->delete();
        DB::statement("ALTER TABLE users MODIFY role ENUM('admin','intern','coordinator','supervisor','dean','chair') NOT NULL DEFAULT 'intern'");
    }

    public function down(): void
    {
        DB::statement("ALTER TABLE users MODIFY role ENUM('admin','intern','coordinator','supervisor','dean','chair','office') NOT NULL DEFAULT 'intern'");
    }
};
