<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Adds:
 *  - `offices` table: inside-campus offices and outside-campus partner
 *    companies/organizations that host interns.
 *  - `supervisor` role (Office/Company representative — manages interns
 *    assigned to their specific office and reviews their logbook entries).
 *  - `office_id` on `users`: for interns, which office they're placed at;
 *    for supervisors, which office they represent.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('offices', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->enum('campus_affiliation', ['inside_campus', 'outside_campus'])->default('inside_campus');
            $table->string('address')->nullable();
            $table->string('contact_person')->nullable();
            $table->string('contact_email')->nullable();
            $table->string('contact_phone')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        DB::statement("ALTER TABLE users MODIFY role ENUM('admin','office','coordinator','supervisor','intern') NOT NULL DEFAULT 'intern'");

        Schema::table('users', function (Blueprint $table) {
            $table->foreignId('office_id')->nullable()->after('coordinator_id')->constrained('offices')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropConstrainedForeignId('office_id');
        });

        DB::statement("ALTER TABLE users MODIFY role ENUM('admin','office','coordinator','intern') NOT NULL DEFAULT 'intern'");

        Schema::dropIfExists('offices');
    }
};
