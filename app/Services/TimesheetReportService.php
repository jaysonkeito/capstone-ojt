<?php

namespace App\Services;

use App\Models\DocumentTemplate;
use App\Models\OjtLog;
use App\Models\TimesheetCertification;
use App\Models\User;
use App\Support\RenderedDocument;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

/**
 * Compiles an intern's current OJT set into the school's Timesheet — every
 * duty day (date, AM/PM times, hours) with the running total against the
 * target, plus the prepared/approved/reviewed signatures. Fills the admin's
 * uploaded Word template; returns null when no Timesheet template has been
 * uploaded yet (the form stays unavailable until an admin adds one).
 */
class TimesheetReportService
{
    public function __construct(private DocumentTemplateService $templates) {}

    /**
     * The finished timesheet as a ready-to-stream Word document, or null when
     * no admin has uploaded a Timesheet template yet.
     */
    public function render(User $intern): ?RenderedDocument
    {
        $templatePath = $this->templates->resolvePath(DocumentTemplate::TYPE_TIMESHEET);

        if (! $templatePath) {
            return null;
        }

        $enrollment = $intern->currentEnrollment;
        // Oldest duty day first so the timesheet reads top-to-bottom in
        // calendar order (earliest day in the upper rows, latest at the foot),
        // the way a Daily Time Record is filled out and signed.
        $logs = $enrollment
            ? $enrollment->logs()->orderBy('date')->get()
            : collect();

        $targetHours = $enrollment?->target_hours ?? $intern->target_hours;
        $lastDate = $logs->count() ? $logs->max('date') : null;

        // The supervisor representing the intern's office signs as the
        // company's authorized representative.
        $supervisor = $intern->office?->supervisors()->first();

        // The most recent per-period certification of this set — fills the
        // certified_* keys when the office supervisor has signed a period off.
        $certification = TimesheetCertification::query()
            ->where('intern_id', $intern->id)
            ->when($enrollment, fn ($q) => $q->where('ojt_enrollment_id', $enrollment->id))
            ->with('supervisor')
            ->latest('certified_at')
            ->first();

        $binary = $this->templates->fillTimesheet(
            $templatePath,
            $this->fields($intern, $logs, $targetHours, $supervisor, $certification),
            $this->rows($logs),
        );

        return RenderedDocument::word($binary, $this->filename($intern, $lastDate, 'docx'));
    }

    /**
     * The Word template's non-repeating fields.
     *
     * @param  Collection<int, OjtLog>  $logs
     * @return array<string, string>
     */
    private function fields(User $intern, Collection $logs, int $targetHours, ?User $supervisor, ?TimesheetCertification $certification = null): array
    {
        $totalMinutes = (int) round(((float) $logs->sum('hours_rendered')) * 60);

        $company = $intern->office?->name ?? '—';
        if ($intern->office?->address) {
            $company .= ' ('.$intern->office->address.')';
        }

        $approved = $supervisor?->display_name ?? '';
        if ($approved !== '' && $supervisor?->title) {
            $approved .= ', '.$supervisor->title;
        }

        $reviewed = $intern->coordinator?->display_name ?? '';
        if ($reviewed !== '' && $intern->coordinator?->title) {
            $reviewed .= ', '.$intern->coordinator->title;
        }

        // The certification block — the office supervisor's per-period
        // sign-off (see App\Support\TimesheetCertifier). Empty until a
        // period has actually been certified.
        $certifiedName = $certification?->supervisor?->display_name ?? '';
        if ($certifiedName !== '' && $certification?->supervisor?->title) {
            $certifiedName .= ', '.$certification->supervisor->title;
        }

        return [
            'intern_name' => (string) $intern->display_name,
            'year_degree' => $intern->course_name.' - '.($intern->year_level_roman ?? '—'),
            'company_address' => $company,
            'required_hours' => (string) $targetHours,
            'total_hours' => $this->formatMinutes($totalMinutes),
            'prepared_name' => (string) $intern->display_name,
            'approved_name' => $approved,
            'reviewed_name' => $reviewed,
            'certified_name' => $certifiedName,
            'certified_period' => $certification?->period_label ?? '',
            'certified_on' => $certification?->certified_at?->format('F j, Y') ?? '',
        ];
    }

    /**
     * One cloned table row per duty day, matching the timesheet layout.
     *
     * @param  Collection<int, OjtLog>  $logs
     * @return array<int, array{date: string, time: string, hours: string}>
     */
    public function rows(Collection $logs): array
    {
        return $logs->map(function (OjtLog $log): array {
            $parts = [];
            if ($log->am_time_in) {
                $parts[] = $this->formatTime($log->am_time_in).' – '.$this->formatTime($log->am_time_out);
            }
            // A completed stepped-out gap pair prints as its own interval —
            // "8:00 AM – 10:30 AM / 11:00 AM – 12:00 PM / ..." — so the
            // returned-and-worked stretch is visible on the signed sheet.
            if ($log->am_time_in_2 && $log->am_time_out_2) {
                $parts[] = $this->formatTime($log->am_time_in_2).' – '.$this->formatTime($log->am_time_out_2);
            }
            if ($log->pm_time_in) {
                $parts[] = $this->formatTime($log->pm_time_in).' – '.$this->formatTime($log->pm_time_out);
            }
            if ($log->pm_time_in_2 && $log->pm_time_out_2) {
                $parts[] = $this->formatTime($log->pm_time_in_2).' – '.$this->formatTime($log->pm_time_out_2);
            }

            return [
                'date' => $log->date->format('m-d-Y'),
                'time' => implode(' / ', $parts) ?: '—',
                'hours' => $this->formatMinutes((int) round(((float) $log->hours_rendered) * 60)),
            ];
        })->all();
    }

    /**
     * "Timesheet_Lastname_Firstname_2026.{ext}".
     */
    private function filename(User $intern, ?Carbon $lastDate, string $extension): string
    {
        $year = $lastDate?->year ?? now()->year;
        $namePart = Str::of("{$intern->last_name} {$intern->first_name}")
            ->replaceMatches('/[^A-Za-z0-9]+/', '_')
            ->trim('_');

        return "Timesheet_{$namePart}_{$year}.{$extension}";
    }

    /**
     * Clock value as "h:i A", or an empty string when not recorded.
     */
    private function formatTime(mixed $value): string
    {
        return $value ? Carbon::parse($value)->format('h:i A') : '';
    }

    /**
     * Whole minutes as "X hours Y minutes", dropping either part when zero so
     * a partial hour like 10h05m is never lost.
     */
    public function formatMinutes(int $minutes): string
    {
        $hours = intdiv($minutes, 60);
        $mins = $minutes % 60;

        $parts = [];
        if ($hours > 0) {
            $parts[] = $hours.' '.Str::plural('hour', $hours);
        }
        if ($mins > 0) {
            $parts[] = $mins.' '.Str::plural('minute', $mins);
        }

        return $parts ? implode(' ', $parts) : '0 hours';
    }
}
