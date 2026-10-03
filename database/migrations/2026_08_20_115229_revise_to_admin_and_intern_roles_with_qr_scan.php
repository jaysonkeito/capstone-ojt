<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

/**
 * Full system revision — System Admin + Intern are the only roles left.
 *
 *  - Deletes the office/coordinator/supervisor staff accounts (every FK
 *    pointing at users is nullOnDelete, so logs and no-class days they
 *    created survive with the reference nulled).
 *  - Shrinks the `role` enum to admin|intern.
 *  - Retires the coordinator join-class flow: rejected enrollments are
 *    removed, pending ones promoted to active (an admin now creates sets
 *    directly — there is no approval step anymore), and the ojt_classes
 *    table plus every office/class/coordinator column goes away.
 *  - Removes the supervisor/coordinator review workflow from ojt_logs —
 *    admin edit is the only correction mechanism now.
 *  - Adds the QR time-in/out token to ojt_settings: the printed poster
 *    encodes /scan/{qr_token}, and rotating the token invalidates every
 *    old poster.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::table('users')->whereIn('role', ['office', 'coordinator', 'supervisor'])->delete();

        DB::statement("ALTER TABLE users MODIFY role ENUM('admin','intern') NOT NULL DEFAULT 'intern'");

        DB::table('ojt_enrollments')->where('status', 'rejected')->delete();
        DB::table('ojt_enrollments')->where('status', 'pending')->update(['status' => 'active']);

        Schema::table('ojt_enrollments', function (Blueprint $table) {
            $table->dropConstrainedForeignId('ojt_class_id');
            $table->dropConstrainedForeignId('office_id');
            $table->dropConstrainedForeignId('coordinator_id');
        });

        Schema::table('users', function (Blueprint $table) {
            $table->dropConstrainedForeignId('coordinator_id');
            $table->dropConstrainedForeignId('office_id');
        });

        Schema::dropIfExists('ojt_classes');
        Schema::dropIfExists('offices');

        Schema::table('ojt_logs', function (Blueprint $table) {
            $table->dropConstrainedForeignId('reviewed_by');
            $table->dropColumn(['status', 'review_comment', 'reviewed_at']);
        });

        Schema::table('ojt_settings', function (Blueprint $table) {
            $table->string('qr_token', 64)->nullable()->unique()->after('working_days');
            $table->timestamp('qr_rotated_at')->nullable()->after('qr_token');
        });

        DB::table('ojt_settings')->whereNull('qr_token')->update([
            'qr_token' => Str::random(40),
            'qr_rotated_at' => now(),
        ]);
    }

    public function down(): void
    {
        // Structural restore only — deleted staff accounts, offices,
        // classes, and review data cannot be brought back here; restore
        // from a pre-revision database backup instead.
        Schema::table('ojt_settings', function (Blueprint $table) {
            $table->dropColumn(['qr_token', 'qr_rotated_at']);
        });

        Schema::table('ojt_logs', function (Blueprint $table) {
            $table->enum('status', ['pending', 'approved', 'rejected'])->default('approved')->after('notes');
            $table->foreignId('reviewed_by')->nullable()->after('status')->constrained('users')->nullOnDelete();
            $table->text('review_comment')->nullable()->after('reviewed_by');
            $table->timestamp('reviewed_at')->nullable()->after('review_comment');
        });

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

        Schema::create('ojt_classes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('coordinator_id')->constrained('users')->cascadeOnDelete();
            $table->string('label');
            $table->unsignedInteger('target_hours');
            $table->string('join_code', 6)->unique();
            $table->boolean('is_open')->default(true);
            $table->timestamps();
        });

        Schema::table('users', function (Blueprint $table) {
            $table->foreignId('coordinator_id')->nullable()->after('role')->constrained('users')->nullOnDelete();
            $table->foreignId('office_id')->nullable()->after('coordinator_id')->constrained('offices')->nullOnDelete();
        });

        Schema::table('ojt_enrollments', function (Blueprint $table) {
            $table->foreignId('ojt_class_id')->nullable()->after('user_id')->constrained('ojt_classes')->nullOnDelete();
            $table->foreignId('office_id')->nullable()->constrained('offices')->nullOnDelete();
            $table->foreignId('coordinator_id')->nullable()->constrained('users')->nullOnDelete();
        });

        DB::statement("ALTER TABLE users MODIFY role ENUM('admin','office','coordinator','supervisor','intern') NOT NULL DEFAULT 'intern'");
    }
};
