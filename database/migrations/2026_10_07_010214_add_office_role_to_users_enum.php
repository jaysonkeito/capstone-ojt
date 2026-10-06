<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * The "office" role: a scanner-only account for the front-desk kiosk PC.
     * It can run the station and nothing else — no dashboards, no intern
     * lists — so the browser left open on the desk all day exposes no
     * supervisor navigation to whoever walks up to it.
     */
    public function up(): void
    {
        DB::statement("ALTER TABLE users MODIFY role ENUM('admin','intern','coordinator','supervisor','dean','office') NOT NULL DEFAULT 'intern'");
    }

    public function down(): void
    {
        DB::table('users')->where('role', 'office')->delete();
        DB::statement("ALTER TABLE users MODIFY role ENUM('admin','intern','coordinator','supervisor','dean') NOT NULL DEFAULT 'intern'");
    }
};
