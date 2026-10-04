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
        // The colleges this installation recognizes. Each college owns its
        // own document template set (scoped by code on document_templates and
        // the templates/{college}/ folders) and appears as a tab on the
        // Templates manager.
        Schema::create('colleges', function (Blueprint $table) {
            $table->id();
            $table->string('code', 20)->unique();
            $table->string('name', 160);
            $table->timestamps();
        });

        DB::table('colleges')->insert([
            'code' => 'cas',
            'name' => 'College of Arts and Sciences',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('colleges');
    }
};
