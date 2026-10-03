<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The intern-entered details behind the school's "Student Intern's Personal
 * Information" requirement form — the fields the app never captured before
 * (birth details, addresses, contact numbers, family background). An intern
 * fills these in once on their profile; the requirement form then mail-merges
 * them per-intern instead of shipping one student's data baked into the .docx.
 *
 * One row per intern (unique user_id). Every field is nullable so the sheet
 * can be completed a little at a time — anything still blank prints as "N/A"
 * on the generated form.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('intern_personal_infos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->unique()->constrained('users')->cascadeOnDelete();

            // Personal
            $table->string('middle_name')->nullable();
            $table->date('birthdate')->nullable();
            $table->string('sex')->nullable();
            $table->string('height')->nullable();
            $table->string('weight')->nullable();
            $table->string('complexion')->nullable();
            $table->string('disability')->nullable();
            $table->string('birth_place')->nullable();
            $table->string('citizenship')->nullable();
            $table->string('civil_status')->nullable();
            $table->string('present_address')->nullable();
            $table->string('present_contact')->nullable();
            $table->string('permanent_address')->nullable();
            $table->string('permanent_contact')->nullable();

            // Family background
            $table->string('father_name')->nullable();
            $table->string('father_occupation')->nullable();
            $table->string('mother_name')->nullable();
            $table->string('mother_occupation')->nullable();
            $table->string('parents_address')->nullable();
            $table->string('parents_contact')->nullable();
            $table->string('guardian_name')->nullable();
            $table->string('guardian_contact')->nullable();

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('intern_personal_infos');
    }
};
