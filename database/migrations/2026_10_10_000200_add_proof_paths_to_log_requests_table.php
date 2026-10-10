<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Up to two photos attached to an attendance request as proof — the
     * absent day's excuse note, a photo of the corrected entry, whatever
     * the intern's supervisor needs to see beside the reason.
     */
    public function up(): void
    {
        Schema::table('log_requests', function (Blueprint $table) {
            $table->json('proof_paths')->nullable()->after('reason');
        });
    }

    public function down(): void
    {
        Schema::table('log_requests', function (Blueprint $table) {
            $table->dropColumn('proof_paths');
        });
    }
};
