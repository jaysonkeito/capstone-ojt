<?php

namespace App\Services;

use App\Models\OjtLog;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Facades\Storage;

/**
 * Compiles an intern's Daily Report — the day's documentation (proof
 * photo, notes, clock times, hours) — into a PDF. Shared by the intern's
 * own export and the admin preview/download modal, so the report looks
 * and behaves identically everywhere.
 */
class DailyReportService
{
    /**
     * The finished PDF as a binary string.
     */
    public function binary(OjtLog $log): string
    {
        return Pdf::loadHTML($this->html($log))->setPaper('a4', 'portrait')->output();
    }

    /**
     * Several daily reports compiled into ONE PDF — one page per duty day —
     * behind the Daily Journal tab's Daily / Weekly / Range / All exports.
     * Each day's report is rendered with the exact same template as the
     * single export; the sections are stacked with page breaks between.
     *
     * @param  iterable<int, OjtLog>  $logs
     */
    public function binaryForLogs(iterable $logs): string
    {
        $sections = [];

        foreach ($logs as $log) {
            $sections[] = '<div style="page-break-after: always;">'.$this->html($log).'</div>';
        }

        return Pdf::loadHTML(implode('', $sections))->setPaper('a4', 'portrait')->output();
    }

    /**
     * The report's rendered HTML — proof photo embedded as a base64 data
     * URI so it always renders inside the PDF, regardless of the app's
     * public URL or host. The MIME type is sniffed from the file's bytes
     * (not the storage driver's guess, which can come back empty) so DomPDF
     * can decode it.
     */
    private function html(OjtLog $log): string
    {
        $log->loadMissing(['user', 'loggedBy', 'ojtEnrollment']);

        // A soft-removed journal counts as missing — the photo stays on
        // disk for undo, but it must not appear in the report.
        $photoDataUri = null;
        if ($log->has_journal && Storage::disk('public')->exists($log->photo_path)) {
            $bytes = (string) Storage::disk('public')->get($log->photo_path);
            $mime = (new \finfo(FILEINFO_MIME_TYPE))->buffer($bytes) ?: 'image/jpeg';
            $photoDataUri = 'data:'.$mime.';base64,'.base64_encode($bytes);
        }

        return view('intern.report', [
            'intern' => $log->user,
            'log' => $log,
            'photoDataUri' => $photoDataUri,
            'generatedAt' => now(),
        ])->render();
    }

    /**
     * Suggested download/preview filename for the report.
     */
    public function filename(OjtLog $log): string
    {
        return "DailyReport_{$log->user->student_id}_{$log->date->format('Y-m-d')}.pdf";
    }
}
