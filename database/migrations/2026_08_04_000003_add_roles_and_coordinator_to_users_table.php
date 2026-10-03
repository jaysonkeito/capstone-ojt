<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Expands the role system from [admin, intern] to
 * [admin, office, coordinator, intern], and adds a self-referencing
 * `coordinator_id` on `users` so an intern can be assigned to the
 * coordinator who supervises them.
 *
 * We use a raw ALTER TABLE for the enum change instead of ->change()
 * so this works without requiring doctrine/dbal.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::statement("ALTER TABLE users MODIFY role ENUM('admin','office','coordinator','intern') NOT NULL DEFAULT 'intern'");

        Schema::table('users', function (Blueprint $table) {
            $table->foreignId('coordinator_id')
                ->nullable()
                ->after('role')
                ->constrained('users')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropConstrainedForeignId('coordinator_id');
        });

        DB::statement("ALTER TABLE users MODIFY role ENUM('admin','intern') NOT NULL DEFAULT 'intern'");
    }
};
