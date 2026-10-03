<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Each intern carries a personal QR — shown on their phone or a printed
     * ID card — that they present to the fixed desk scanner at the office to
     * time in/out. This column holds the secret token that QR encodes; it is
     * generated lazily the first time an intern opens their code (see
     * User::scanToken()), so no backfill is needed. Nullable because staff and
     * monitor accounts never get one; unique so a scanned token maps to exactly
     * one intern.
     */
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('scan_token', 64)->nullable()->unique()->after('remember_token');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropUnique(['scan_token']);
            $table->dropColumn('scan_token');
        });
    }
};
