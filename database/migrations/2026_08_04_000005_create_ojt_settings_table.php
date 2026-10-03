<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ojt_settings', function (Blueprint $table) {
            $table->id();
            $table->time('am_time_in')->default('08:00:00');
            $table->time('am_time_out')->default('12:00:00');
            $table->time('pm_time_in')->default('13:00:00');
            $table->time('pm_time_out')->default('17:00:00');
            $table->unsignedInteger('grace_period_minutes')->default(15);
            $table->date('absence_start_date')->nullable();
            // Stored as a JSON array of Carbon dayOfWeek numbers (0=Sun..6=Sat),
            // e.g. [1,2,3,4,5] = Monday through Friday. No DB-level default
            // here (JSON column defaults are finicky pre-MySQL 8.0.13) —
            // App\Models\OjtSetting::current() sets the default in code instead.
            $table->json('working_days')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ojt_settings');
    }
};
