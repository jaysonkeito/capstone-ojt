<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Staff announcements. The audience is decided by the author's role at
     * creation time: the System Admin reaches every intern, deans and
     * Program Chairs their college, supervisors their office, coordinators
     * their class — each scope snapshotted onto the row.
     */
    public function up(): void
    {
        Schema::create('announcements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('author_id')->constrained('users')->cascadeOnDelete();
            $table->enum('audience', ['all', 'college', 'office', 'class']);
            $table->string('college_code', 20)->nullable();
            $table->foreignId('office_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('coordinator_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('title', 150);
            $table->text('body');
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index(['is_active', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('announcements');
    }
};
