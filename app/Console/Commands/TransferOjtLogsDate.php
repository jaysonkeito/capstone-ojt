<?php

namespace App\Console\Commands;

use App\Models\OjtLog;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Moves OJT log entries from one calendar date to another. Only the `date`
 * column is rewritten — clocked times and computed hours are left exactly as
 * they are — so this is a pure "these entries belong on a different day" fix.
 *
 * Because `ojt_logs` is unique on (user_id, date), any intern who already has
 * an entry on the destination date is reported and skipped rather than
 * overwritten. Use --dry-run to preview, and --student to scope to one intern.
 */
class TransferOjtLogsDate extends Command
{
    /**
     * @var string
     */
    protected $signature = 'ojt:transfer-logs
        {from : Source date whose logs should be moved (e.g. 2026-08-17)}
        {to : Destination date to move them onto (e.g. 2026-08-18)}
        {--student= : Limit to a single intern by their student_id}
        {--dry-run : Show what would move without writing any changes}';

    /**
     * @var string
     */
    protected $description = 'Move OJT log entries from one date to another (date only; times and hours are untouched).';

    public function handle(): int
    {
        try {
            $from = Carbon::parse($this->argument('from'))->toDateString();
            $to = Carbon::parse($this->argument('to'))->toDateString();
        } catch (\Throwable) {
            $this->error('Both dates must be valid calendar dates, e.g. 2026-08-17.');

            return self::FAILURE;
        }

        if ($from === $to) {
            $this->error("Source and destination dates are identical ({$from}) — nothing to move.");

            return self::FAILURE;
        }

        $student = $this->option('student');

        $logs = OjtLog::with('user')
            ->whereDate('date', $from)
            ->when($student, function ($query) use ($student) {
                return $query->whereHas('user', function ($userQuery) use ($student) {
                    $userQuery->where('student_id', $student);
                });
            })
            ->orderBy('user_id')
            ->get();

        if ($logs->isEmpty()) {
            $this->warn("No OJT logs found on {$from}".($student ? " for student {$student}." : '.'));

            return self::SUCCESS;
        }

        $movableIds = [];
        $movableRows = [];
        $conflictRows = [];

        foreach ($logs as $log) {
            $row = [
                $log->id,
                $log->user?->student_id ?? '—',
                trim(($log->user?->first_name ?? '').' '.($log->user?->last_name ?? '')) ?: '—',
                (string) $log->hours_rendered,
            ];

            $destinationTaken = OjtLog::where('user_id', $log->user_id)
                ->whereDate('date', $to)
                ->exists();

            if ($destinationTaken) {
                $conflictRows[] = $row;
            } else {
                $movableIds[] = $log->id;
                $movableRows[] = $row;
            }
        }

        $this->info(sprintf(
            'Found %d log(s) on %s — %d to move, %d blocked by an existing %s entry.',
            $logs->count(),
            $from,
            count($movableRows),
            count($conflictRows),
            $to,
        ));

        if ($movableRows !== []) {
            $this->table(['Log ID', 'Student ID', 'Name', 'Hours'], $movableRows);
        }

        if ($conflictRows !== []) {
            $this->warn("Skipped — these interns already have a {$to} entry (one log per intern per date):");
            $this->table(['Log ID', 'Student ID', 'Name', 'Hours'], $conflictRows);
        }

        if ($this->option('dry-run')) {
            $this->comment('Dry run — no changes written. Re-run without --dry-run to apply.');

            return self::SUCCESS;
        }

        if ($movableIds === []) {
            $this->warn('Nothing to move.');

            return self::SUCCESS;
        }

        DB::transaction(function () use ($movableIds, $to) {
            OjtLog::whereIn('id', $movableIds)->update([
                'date' => $to,
                'updated_at' => now(),
            ]);
        });

        $this->info(sprintf('Moved %d log(s) from %s to %s.', count($movableIds), $from, $to));

        return self::SUCCESS;
    }
}
