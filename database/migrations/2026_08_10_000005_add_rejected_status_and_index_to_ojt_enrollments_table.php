<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Previously, declining a join request deleted the `ojt_enrollments` row
 * outright — simple, but it meant there was no record of who was
 * declined, from which class, or when. This adds a `rejected` status so
 * OjtEnrollmentService::rejectJoinRequest() can keep the row instead.
 *
 * A rejected row is deliberately excluded from User::currentEnrollment()
 * (see the model change alongside this migration) so it can never count
 * as an intern's "open" set — they can immediately try joining again.
 *
 * Also adds the (ojt_class_id, status) index that
 * Coordinator\OjtClassController regularly filters by, now that this
 * table is being touched anyway.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::statement("ALTER TABLE ojt_enrollments MODIFY status ENUM('pending','active','completed','rejected') NOT NULL DEFAULT 'pending'");

        Schema::table('ojt_enrollments', function (Blueprint $table) {
            $table->index(['ojt_class_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::table('ojt_enrollments', function (Blueprint $table) {
            $table->dropIndex(['ojt_class_id', 'status']);
        });

        DB::statement("ALTER TABLE ojt_enrollments MODIFY status ENUM('pending','active','completed') NOT NULL DEFAULT 'pending'");
    }
};
