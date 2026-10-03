<?php

namespace App\Http\Controllers\Intern;

use App\Http\Controllers\Controller;
use App\Models\OjtLog;
use App\Services\DailyReportService;
use App\Services\TimesheetReportService;
use App\Services\WeeklyProgressReportService;
use chillerlan\QRCode\Common\EccLevel;
use chillerlan\QRCode\Output\QRGdImagePNG;
use chillerlan\QRCode\QRCode;
use chillerlan\QRCode\QROptions;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\HeaderUtils;

class DashboardController extends Controller
{
    public function index(Request $request)
    {
        $intern = $request->user();
        $currentEnrollment = $intern->currentEnrollment;

        // Only the CURRENT set's logs drive the calendar, stat cards, and
        // log history below — an earlier completed set (possibly a
        // different target) is shown separately as read-only history,
        // never mixed into these numbers.
        $logs = $currentEnrollment
            ? $currentEnrollment->logs()->orderByDesc('date')->get()
            : collect();

        // Every past OJT set the intern has completed, most recent first,
        // each with its own self-contained hour count.
        $pastEnrollments = $intern->enrollments()
            ->where('id', '!=', $currentEnrollment?->id)
            ->get();

        // Today's entry — drives the duty-day photo/notes card at the top
        // of the dashboard (upload becomes required once a clock-out is
        // recorded on the entry).
        $todayLog = OjtLog::where('user_id', $intern->id)
            ->whereDate('date', today())
            ->first();

        // Build the duty-day calendar for the requested month (defaults to current month).
        $month = $request->filled('month')
            ? Carbon::createFromFormat('Y-m', $request->get('month'))->startOfMonth()
            : now()->startOfMonth();

        $logsByDate = $logs->keyBy(fn ($log) => $log->date->format('Y-m-d'));

        $calendarStart = $month->copy()->startOfMonth()->startOfWeek(Carbon::SUNDAY);
        $calendarEnd = $month->copy()->endOfMonth()->endOfWeek(Carbon::SATURDAY);

        $calendarDays = [];
        $cursor = $calendarStart->copy();
        while ($cursor->lte($calendarEnd)) {
            $key = $cursor->format('Y-m-d');
            $calendarDays[] = [
                'date' => $cursor->copy(),
                'in_month' => $cursor->month === $month->month,
                'log' => $logsByDate->get($key),
            ];
            $cursor->addDay();
        }

        // Split into weeks of 7 for the Blade grid.
        $calendarWeeks = array_chunk($calendarDays, 7);

        return view('intern.dashboard', [
            'intern' => $intern,
            'currentEnrollment' => $currentEnrollment,
            'pastEnrollments' => $pastEnrollments,
            'logs' => $logs,
            'todayLog' => $todayLog,
            'month' => $month,
            'calendarWeeks' => $calendarWeeks,
            'prevMonth' => $month->copy()->subMonth()->format('Y-m'),
            'nextMonth' => $month->copy()->addMonth()->format('Y-m'),
        ]);
    }

    /**
     * The intern's personal time-in/out QR — the code the office desk
     * scanner reads. Unlike the shared wall poster (a URL anyone can
     * photograph), this encodes the intern's own secret token, so it only
     * works when the intern is physically at the scanner. Shown on-screen
     * for day-to-day use and as a print-ready ID card to keep in a wallet
     * or lanyard as a backup when the phone's battery is flat.
     */
    public function myQr(Request $request)
    {
        $intern = $request->user();

        // High error correction so the code still reads when a printed card
        // gets scuffed or a phone screen is smudged; scale 12 keeps it crisp
        // both on-screen and on the ~2" card.
        $options = new QROptions([
            'outputInterface' => QRGdImagePNG::class,
            'eccLevel' => EccLevel::H,
            'scale' => 12,
        ]);

        $qrDataUri = (new QRCode($options))->render($intern->scanQrPayload());

        return view('intern.my-qr', [
            'intern' => $intern,
            'qrDataUri' => $qrDataUri,
        ]);
    }

    /**
     * The intern's Daily Report for one of their own duty days — the day's
     * documentation (proof photo, notes, clock times, hours, audit status)
     * compiled into a .pdf and streamed straight to the browser as a
     * download. Read-only, so any of the intern's own entries works,
     * including past sets.
     */
    public function showReport(Request $request, OjtLog $log): Response
    {
        abort_unless($log->user_id === $request->user()->id, 404);

        $service = new DailyReportService;
        $binary = $service->binary($log);
        $filename = $service->filename($log);

        return new Response($binary, 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => HeaderUtils::makeDisposition('attachment', $filename, $filename),
            'Content-Length' => strlen($binary),
        ]);
    }

    /**
     * The intern's Time Frame export — every duty day of their CURRENT OJT
     * set (date, AM/PM times, hours) with the running total against the
     * set's target hours, streamed as the admin's Word template filled with
     * the intern's data. Backed by the Export button on the My Time Frame
     * page; redirects back with a notice when no Time Frame template has
     * been uploaded yet.
     */
    public function timesheet(Request $request, TimesheetReportService $service): Response|RedirectResponse
    {
        $document = $service->render($request->user());

        if (! $document) {
            return redirect()
                ->route('intern.dashboard')
                ->with('status', "Your Time Frame isn't available yet — your OJT coordinator hasn't set up the Time Frame template.");
        }

        return $this->streamDocument($document, $request->boolean('download'));
    }

    /**
     * The intern's journals exported as the school's Word Weekly Progress
     * Report — daily journal entries (message + Documentation photo)
     * compiled into the admin's uploaded template, one page per
     * Monday-start week, exactly the document behind the My Journal page's
     * "Export Journal" button. Redirects back with a notice when no Weekly
     * Progress Report template has been uploaded yet.
     */
    public function exportJournals(Request $request, WeeklyProgressReportService $service): Response|RedirectResponse
    {
        $intern = $request->user();

        if ($intern->ojtLogs()->count() === 0) {
            return redirect()
                ->route('intern.documentation')
                ->with('status', "You don't have any journals on record yet — upload your first one from this page.");
        }

        $document = $service->render($intern);

        if (! $document) {
            return redirect()
                ->route('intern.documentation')
                ->with('status', "Your Weekly Progress Report isn't available yet — your OJT coordinator hasn't set up the Weekly Progress Report template.");
        }

        // Always downloads as "MyJournal_Lastname_Firstname_year.docx"
        // (year of the latest journal), whatever the underlying template's
        // own filename suggestion is.
        $year = $intern->ojtLogs()->max('date')
            ? Carbon::parse($intern->ojtLogs()->max('date'))->year
            : now()->year;
        $namePart = Str::of("{$intern->last_name} {$intern->first_name}")
            ->replaceMatches('/[^A-Za-z0-9]+/', '_')
            ->trim('_');

        return new Response($document->binary, 200, [
            'Content-Type' => $document->mime,
            'Content-Disposition' => HeaderUtils::makeDisposition('attachment', "MyJournal_{$namePart}_{$year}.docx", "MyJournal_{$namePart}_{$year}.docx"),
            'Cache-Control' => 'no-store, no-cache, must-revalidate',
        ]);
    }

    /**
     * The My Time Frame page — every duty day of the intern's CURRENT OJT
     * set (date, AM/PM times, hours) with the running total against the
     * set's target hours, readable on screen instead of requiring the Word
     * download. TimesheetReportService supplies the rows and totals, so
     * this page always matches the exported form line for line.
     */
    public function timeFrame(Request $request, TimesheetReportService $service): View
    {
        $intern = $request->user();
        $enrollment = $intern->currentEnrollment;

        // Oldest duty day first — the same calendar order the Word export
        // prints, so the on-screen page reads exactly like the signed form.
        $logs = $enrollment
            ? $enrollment->logs()->orderBy('date')->get()
            : collect();

        $renderedHours = (float) $logs->sum('hours_rendered');
        $targetHours = $enrollment?->target_hours ?? $intern->target_hours;

        return view('intern.time-frame', [
            'intern' => $intern,
            'enrollment' => $enrollment,
            'rows' => $service->rows($logs),
            'renderedHours' => $renderedHours,
            'targetHours' => $targetHours,
            'totalHours' => $service->formatMinutes((int) round($renderedHours * 60)),
        ]);
    }

    /**
     * The intern's Weekly Progress Report — every duty day of their CURRENT
     * OJT set grouped into fixed Monday–Friday weeks (one page each), with that
     * day's journal message and Documentation photo, streamed as the admin's
     * Word template filled with the intern's data. Redirects back to the
     * dashboard with a notice when no Weekly Progress Report template has been
     * uploaded yet.
     */
    public function weeklyReport(Request $request, WeeklyProgressReportService $service): Response|RedirectResponse
    {
        $document = $service->render($request->user());

        if (! $document) {
            return redirect()
                ->route('intern.dashboard')
                ->with('status', "Your Weekly Progress Report isn't available yet — your OJT coordinator hasn't set up the Weekly Progress Report template.");
        }

        return $this->streamDocument($document, $request->boolean('download'));
    }

    /**
     * The intern uploads or edits their single proof photo + notes for a
     * specific day's entry (today or any past day still missing its photo).
     * The entry must be one of their own, on their CURRENT OJT set (earlier,
     * completed sets are frozen read-only history), with a clock-out
     * (AM OUT or PM OUT) already recorded. A new upload replaces the
     * previous photo; the photo is optional when editing an entry that
     * already has one, so the journal message can be corrected alone.
     */
    public function storePhoto(Request $request, OjtLog $log)
    {
        $intern = $request->user();

        abort_unless($log->user_id === $intern->id, 404);

        $currentEnrollment = $intern->currentEnrollment;

        abort_unless($currentEnrollment && $currentEnrollment->id === $log->ojt_enrollment_id, 422, "That entry belongs to a completed OJT set — it can't be edited anymore.");

        abort_unless($log->clocked_out, 422, 'Photo upload unlocks once the AM or PM clock-out has been recorded.');

        abort_if($log->date?->isFuture(), 422, "Can't upload a photo for a future date.");

        $validated = $request->validate([
            // A first-time upload must include the proof photo; editing an
            // entry that already has one may keep it (leave the file empty).
            // A soft-removed journal counts as "no live journal", so a fresh
            // photo is required to bring the day back — the removed one
            // isn't silently resurrected.
            'photo' => ['nullable', 'image', 'mimes:jpeg,png,jpg,webp', 'max:5120', Rule::requiredIf(! $log->has_journal)],
            'notes' => ['required', 'string', 'max:2000'],
        ], [
            'photo.required' => 'A duty photo is required for a new journal entry.',
            'notes.required' => 'Write a short journal message about what you did this day.',
        ]);

        $photoPath = $log->photo_path;

        if ($request->hasFile('photo')) {
            if ($photoPath) {
                Storage::disk('public')->delete($photoPath);
            }

            // File naming for the documentation archive:
            // lastname_firstname_mm_dd_yyyy.ext (the duty day's date).
            $ext = $request->file('photo')->extension() ?: 'jpg';
            $last = Str::of($intern->last_name)->replaceMatches('/[^A-Za-z0-9]/', '')->lower();
            $first = Str::of($intern->first_name)->replaceMatches('/[^A-Za-z0-9]/', '')->lower();
            $filename = "{$last}_{$first}_".$log->date->format('m_d_Y').".{$ext}";

            $photoPath = $request->file('photo')->storeAs("ojt-photos/{$intern->id}", $filename, 'public');
        }

        $log->update([
            'photo_path' => $photoPath,
            'notes' => $validated['notes'],
            // Saving a journal (re)activates the day — if the previous
            // journal had been soft-removed, this undoes that removal.
            'journal_removed_at' => null,
        ]);

        return back()->with('status', "Daily journal saved for {$log->date->format('M d, Y')}. Entry complete ✅");
    }

    /**
     * The intern removes their journal (proof photo + notes) from a specific
     * day's entry on their CURRENT OJT set. This is a soft delete: the photo
     * file and notes stay in place, only `journal_removed_at` is stamped, so
     * the removal can be undone (see restoreJournal). The day flips back to
     * "awaiting journal" — the duty times stay, and the intern can upload a
     * fresh journal or restore the removed one. Completed sets stay frozen
     * read-only history.
     */
    public function destroyJournal(Request $request, OjtLog $log)
    {
        $intern = $request->user();

        abort_unless($log->user_id === $intern->id, 404);

        $currentEnrollment = $intern->currentEnrollment;

        abort_unless($currentEnrollment && $currentEnrollment->id === $log->ojt_enrollment_id, 422, "That entry belongs to a completed OJT set — it can't be edited anymore.");

        abort_unless($log->has_journal, 422, "This day has no journal to remove.");

        $log->update(['journal_removed_at' => now()]);

        return back()->with('status', "Daily journal archived for {$log->date->format('M d, Y')} — find it under Archive on this page.");
    }

    /**
     * The intern permanently deletes a journal on their CURRENT OJT set —
     * the proof photo is removed from disk and the journal message is
     * erased for good (no archive, no restore). The duty times remain; the
     * day returns to "awaiting journal" so a fresh one can be uploaded.
     * Same guards as archiving: own entry, current set only.
     */
    public function forceDestroyJournal(Request $request, OjtLog $log)
    {
        $intern = $request->user();

        abort_unless($log->user_id === $intern->id, 404);

        $currentEnrollment = $intern->currentEnrollment;

        abort_unless($currentEnrollment && $currentEnrollment->id === $log->ojt_enrollment_id, 422, "That entry belongs to a completed OJT set — it can't be edited anymore.");

        abort_if(! $log->photo_path && $log->notes === null, 422, "This day has no journal to delete.");

        if ($log->photo_path) {
            Storage::disk('public')->delete($log->photo_path);
        }

        $log->update([
            'photo_path' => null,
            'notes' => null,
            'journal_removed_at' => null,
        ]);

        return back()->with('status', "Journal for {$log->date->format('M d, Y')} permanently deleted — the duty times remain, and a new journal can be uploaded for this day.");
    }

    /**
     * The intern undoes a soft-removed journal on their CURRENT OJT set —
     * the proof photo (still on disk) and notes go live again exactly as
     * they were. Same guards as removal: own entry, current set only.
     */
    public function restoreJournal(Request $request, OjtLog $log)
    {
        $intern = $request->user();

        abort_unless($log->user_id === $intern->id, 404);

        $currentEnrollment = $intern->currentEnrollment;

        abort_unless($currentEnrollment && $currentEnrollment->id === $log->ojt_enrollment_id, 422, "That entry belongs to a completed OJT set — it can't be edited anymore.");

        abort_unless($log->journal_removed_at, 422, "This day's journal hasn't been removed.");

        $log->update(['journal_removed_at' => null]);

        return back()->with('status', "Daily journal restored for {$log->date->format('M d, Y')}.");
    }

    /**
     * The intern's documentation archive — EVERY duty day of their current
     * OJT set, newest first, as one uniform archive: journaled days show
     * their proof photo and journal message; days without a journal show a
     * placeholder with an upload form. Journals from earlier sets remain
     * below as read-only archive photos.
     */
    public function documentation(Request $request)
    {
        $intern = $request->user();
        $currentEnrollment = $intern->currentEnrollment;

        $days = $currentEnrollment
            ? $currentEnrollment->logs()->with('ojtEnrollment')->orderByDesc('date')->paginate(24)->withQueryString()
            : OjtLog::query()->whereRaw('0 = 1')->paginate(24);

        // Journals from earlier sets stay as read-only archive photos.
        $archivePhotos = OjtLog::where('user_id', $intern->id)
            ->whereNotNull('photo_path')
            ->whereNull('journal_removed_at')
            ->where(function ($q) use ($currentEnrollment) {
                $q->whereNull('ojt_enrollment_id')
                    ->orWhere('ojt_enrollment_id', '!=', $currentEnrollment?->id ?? 0);
            })
            ->with('ojtEnrollment')
            ->orderByDesc('date')
            ->get();

        // Archived journals (soft-removed) — filed under the page's Archive
        // section until restored or permanently deleted. Any set: restore
        // is only offered for the current set's entries.
        $archivedJournals = OjtLog::where('user_id', $intern->id)
            ->whereNotNull('journal_removed_at')
            ->with('ojtEnrollment')
            ->orderByDesc('journal_removed_at')
            ->get();

        return view('intern.documentation', [
            'intern' => $intern,
            'days' => $days,
            'archivePhotos' => $archivePhotos,
            'archivedJournals' => $archivedJournals,
        ]);
    }
}
