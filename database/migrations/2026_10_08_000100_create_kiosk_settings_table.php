<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Per-office kiosk station controls: the office supervisor (or the
     * System Admin) can lock individual station tabs — Scanner, Camera,
     * Student ID — so the desk operator only gets the modes the office
     * wants available. An office_id of null is the campus-wide default
     * the admin manages; an office's own row overrides it.
     */
    public function up(): void
    {
        Schema::create('kiosk_settings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('office_id')->nullable()->unique()->constrained()->nullOnDelete();
            $table->boolean('lock_scanner')->default(false);
            $table->boolean('lock_camera')->default(false);
            $table->boolean('lock_manual')->default(false);
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('kiosk_settings');
    }
};
