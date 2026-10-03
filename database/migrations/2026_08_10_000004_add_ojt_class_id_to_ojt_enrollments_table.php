<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Links an intern's OJT set to the shared `ojt_classes` row it came from
 * — null when the set was created the other way (Admin/Coordinator
 * directly starting a set for one named intern via "Start New Set"; see
 * App\Support\OjtEnrollmentService::startNewSet). Both origins produce
 * an identical `ojt_enrollments` row otherwise, so every other part of
 * the app (hours, logbook, policies) doesn't need to care which one it was.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('ojt_enrollments', function (Blueprint $table) {
            $table->foreignId('ojt_class_id')->nullable()->after('user_id')
                ->constrained('ojt_classes')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('ojt_enrollments', function (Blueprint $table) {
            $table->dropForeign(['ojt_class_id']);
            $table->dropColumn('ojt_class_id');
        });
    }
};
