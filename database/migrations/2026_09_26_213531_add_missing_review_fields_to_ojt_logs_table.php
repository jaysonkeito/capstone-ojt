<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Repairs the log review/approval workflow columns. The original migration
 * (2026_08_05_000005) is recorded as ran, but the live database lost the
 * columns (schema drift after a restore) — so this guarded migration re-adds
 * them: `status` (pending/approved/rejected), `reviewed_by`, `review_comment`,
 * `reviewed_at`, plus a (status, date) index for the review-queue queries.
 *
 * Every step is conditional, so this is safe to run against a database that
 * somehow still has some (or all) of the columns.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('ojt_logs', function (Blueprint $table) {
            if (! Schema::hasColumn('ojt_logs', 'status')) {
                $table->enum('status', ['pending', 'approved', 'rejected'])->default('approved')->after('notes');
            }

            if (! Schema::hasColumn('ojt_logs', 'reviewed_by')) {
                $table->foreignId('reviewed_by')->nullable()->after('status')->constrained('users')->nullOnDelete();
            }

            if (! Schema::hasColumn('ojt_logs', 'review_comment')) {
                $table->text('review_comment')->nullable()->after('reviewed_by');
            }

            if (! Schema::hasColumn('ojt_logs', 'reviewed_at')) {
                $table->timestamp('reviewed_at')->nullable()->after('review_comment');
            }
        });

        if (! $this->indexExists('ojt_logs', 'ojt_logs_status_date_index')) {
            Schema::table('ojt_logs', function (Blueprint $table) {
                $table->index(['status', 'date']);
            });
        }
    }

    public function down(): void
    {
        Schema::table('ojt_logs', function (Blueprint $table) {
            if ($this->indexExists('ojt_logs', 'ojt_logs_status_date_index')) {
                $table->dropIndex('ojt_logs_status_date_index');
            }

            if (Schema::hasColumn('ojt_logs', 'reviewed_at')) {
                $table->dropColumn('reviewed_at');
            }

            if (Schema::hasColumn('ojt_logs', 'review_comment')) {
                $table->dropColumn('review_comment');
            }

            if (Schema::hasColumn('ojt_logs', 'reviewed_by')) {
                $table->dropConstrainedForeignId('reviewed_by');
            }

            if (Schema::hasColumn('ojt_logs', 'status')) {
                $table->dropColumn('status');
            }
        });
    }

    private function indexExists(string $table, string $index): bool
    {
        $connection = Schema::getConnection();

        return (bool) $connection->select(
            'select 1 from information_schema.statistics where table_schema = ? and table_name = ? and index_name = ? limit 1',
            [$connection->getDatabaseName(), $table, $index],
        );
    }
};
