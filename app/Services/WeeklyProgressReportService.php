<?php

namespace App\Services;

use App\Models\DocumentTemplate;
use App\Models\OjtLog;
use App\Models\User;
use App\Support\RenderedDocument;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Compiles an intern's daily journal entries into the school's Internship
 * Weekly Progress Report. Each page is one Monday-start week laid out as the
 * form's fixed Monday–Friday grid; a weekday is filled in only when that day
 * has a completed journal (a message plus a Documentation photo), otherwise
 * its activity cell and photo box are left blank. Fills the admin's uploaded
 * Word template; returns null when no Weekly Progress Report template has been
 * uploaded yet (the form stays unavailable until an admin adds one).
 */
class WeeklyProgressReportService
{
    public function __construct(private DocumentTemplateService $templates) {}

    /**
     * The finished report as a ready-to-stream Word document, or null when no
     * admin has uploaded a Weekly Progress Report template yet.
     */
    public function render(User $intern): ?RenderedDocument
    {
        $templatePath = $this->templates->resolvePath(DocumentTemplate::TYPE_WEEKLY_PROGRESS_REPORT);

        if (! $templatePath) {
            return null;
        }

        $context = $this->docxContext($intern);
        $binary = $this->templates->fillWeekly($templatePath, $context['globals'], $context['weeks']);

        return RenderedDocument::word($binary, $this->filename($intern, 'docx'));
    }

    /**
     * Group the given logs into Monday-start weeks, ordered oldest first, each
     * labelled "Nth week of <Month Year>" (the ordinal counts within that
     * month, so the season reads naturally across the whole OJT set).
     *
     * @param  Collection<int, OjtLog>  $logs
     * @return Collection<int, array{label: string, month_header: string, monday: Carbon, logs: Collection<int, OjtLog>}>
     */
    public function groupIntoWeeks(Collection $logs): Collection
    {
        $monthOrdinals = [];

        return $logs
            ->sortBy('date')
            ->groupBy(fn (OjtLog $log): string => $log->date->copy()->startOfWeek(Carbon::MONDAY)->format('Y-m-d'))
            ->sortKeys()
            ->map(function (Collection $weekLogs) use (&$monthOrdinals): array {
                $ordered = $weekLogs->sortBy('date')->values();
                $monday = $ordered->first()->date->copy()->startOfWeek(Carbon::MONDAY);
                $monthLabel = $ordered->first()->date->format('F Y');
                $monthOrdinals[$monthLabel] = ($monthOrdinals[$monthLabel] ?? 0) + 1;

                return [
                    'label' => $this->ordinalWord($monthOrdinals[$monthLabel]).' week of '.$monthLabel,
                    'month_header' => $monthLabel,
                    'monday' => $monday,
                    'logs' => $ordered,
                ];
            })
            ->values();
    }

    /**
     * Lay a week's logs onto the school form's fixed Monday–Friday columns.
     * Always returns exactly five slots; a weekday carries its log only when
     * that day has a completed journal (both a Documentation photo and a
     * message), otherwise the slot is blank. This is what keeps the grid at
     * five columns regardless of how many days were actually worked. Each
     * filled slot also carries the day's full date, printed under its photo
     * in the Documentation section via the ${photoN_date} variables.
     *
     * @param  Collection<int, OjtLog>  $weekLogs
     * @return array<int, array{day: string, weekday: string, photo_date: string, log: ?OjtLog}>
     */
    public function weekdaySlots(Carbon $monday, Collection $weekLogs): array
    {
        $monday = $monday->copy()->startOfWeek(Carbon::MONDAY);
        $byDate = $weekLogs->keyBy(fn (OjtLog $log): string => $log->date->format('Y-m-d'));

        $slots = [];
        for ($offset = 0; $offset < 5; $offset++) {
            $date = $monday->copy()->addDays($offset);
            $log = $byDate->get($date->format('Y-m-d'));
            $hasJournal = $log && $log->has_journal && filled($log->notes);

            $slots[] = [
                'day' => $date->format('d'),
                'weekday' => $date->format('l'),
                'photo_date' => $hasJournal ? $date->format('F j, Y') : '',
                'log' => $hasJournal ? $log : null,
            ];
        }

        return $slots;
    }

    /**
     * Suggested download filename for the report.
     */
    public function filename(User $intern, string $extension = 'docx'): string
    {
        $namePart = Str::of("{$intern->last_name} {$intern->first_name}")
            ->replaceMatches('/[^A-Za-z0-9]+/', '_')
            ->trim('_');

        $latest = $intern->currentEnrollment?->logs()->max('date');
        $year = $latest ? Carbon::parse($latest)->year : now()->year;

        return "WeeklyProgressReport_{$namePart}_{$year}.{$extension}";
    }

    /**
     * The data used to mail-merge the Word template: identity fields that
     * repeat on every page, and one entry per week with its fixed
     * Monday–Friday day columns (activity text + Documentation photo path).
     *
     * @return array{globals: array<string, string>, weeks: array<int, array{week_label: string, month_header: string, days: array<int, array{num: string, weekday: string, activity: string, photo: ?string}>}>}
     */
    private function docxContext(User $intern): array
    {
        $enrollment = $intern->currentEnrollment;
        $logs = $enrollment ? $enrollment->logs()->orderBy('date')->get() : collect();

        $targetHours = $enrollment?->target_hours ?? $intern->target_hours;
        $year = $logs->isNotEmpty() ? $logs->max('date')->year : now()->year;

        $globals = [
            'intern_name' => (string) $intern->full_name,
            'cooperating_agency' => (string) ($intern->office?->name ?? ''),
            'course_name' => (string) $intern->course_name,
            'internship_year' => (string) $year,
            'target_hours' => (string) $targetHours,
        ];

        $weeks = $this->groupIntoWeeks($logs)
            ->map(function (array $week): array {
                $days = collect($this->weekdaySlots($week['monday'], $week['logs']))
                    ->map(fn (array $slot): array => [
                        'num' => $slot['day'],
                        'weekday' => $slot['weekday'],
                        'activity' => (string) ($slot['log']?->notes ?? ''),
                        'photo' => $slot['log'] ? $this->photoPath($slot['log']) : null,
                        'photo_date' => $slot['photo_date'],
                    ])
                    ->all();

                return [
                    'week_label' => $week['label'],
                    'month_header' => $week['month_header'],
                    'days' => $days,
                ];
            })
            ->all();

        return ['globals' => $globals, 'weeks' => $weeks];
    }

    /**
     * Absolute filesystem path of a day's Documentation photo for the Word
     * template (PHPWord embeds from a real file, not a data URI), or null when
     * none was uploaded.
     */
    private function photoPath(OjtLog $log): ?string
    {
        // A soft-removed journal counts as missing — the photo stays on
        // disk for undo, but it must not appear in the report.
        if (! $log->has_journal || ! Storage::disk('public')->exists($log->photo_path)) {
            return null;
        }

        return Storage::disk('public')->path($log->photo_path);
    }

    /**
     * Spell out a small week ordinal ("First", "Second", …), falling back to
     * an "Nth" form beyond the mapped range.
     */
    private function ordinalWord(int $n): string
    {
        return [
            1 => 'First',
            2 => 'Second',
            3 => 'Third',
            4 => 'Fourth',
            5 => 'Fifth',
            6 => 'Sixth',
        ][$n] ?? $n.'th';
    }
}
