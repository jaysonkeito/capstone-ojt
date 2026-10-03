<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Adds a review/approval workflow to ojt_logs so Supervisors can approve,
 * reject, or comment on entries submitted for their office's interns.
 *
 * Design note: entries created directly by Admin/Coordinator/Office staff
 * (the current Logbook/kiosk flow) default to 'approved' since a staff
 * member already validated the time when recording it — the review queue
 * is for Supervisor oversight/disputes, not a blocking gate on every entry.
 * `accumulated_hours` still sums ALL logs regardless of status, to avoid
 * changing existing hour totals; `approved_hours`/`pending_hours` are
 * available separately for anyone who wants the approval-aware view.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('ojt_logs', function (Blueprint $table) {
            $table->enum('status', ['pending', 'approved', 'rejected'])->default('approved')->after('notes');
            $table->foreignId('reviewed_by')->nullable()->after('status')->constrained('users')->nullOnDelete();
            $table->text('review_comment')->nullable()->after('reviewed_by');
            $table->timestamp('reviewed_at')->nullable()->after('review_comment');
        });
    }

    public function down(): void
    {
        Schema::table('ojt_logs', function (Blueprint $table) {
            $table->dropConstrainedForeignId('reviewed_by');
            $table->dropColumn(['status', 'review_comment', 'reviewed_at']);
        });
    }
};
