<?php

namespace App\Http\Controllers;

use App\Http\Requests\ManualLogRequest;
use App\Http\Requests\ReviewLogRequest;
use App\Models\OjtLog;
use App\Models\User;
use App\Services\TimesheetReportService;
use App\Services\WeeklyProgressReportService;
use App\Support\LogReview;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

/**
 * The monitoring dashboards shared by the two oversight roles. Both now
 * have write actions within their own scope — everything else stays
 * read-only, and QR scans remain the primary source of time entries.
 *
 *  - OJT Coordinator: every intern assigned to them, with each one's
 *    office, supervisor, and hour progress. Can flag a duty entry for a
 *    second look and export/report on their caseload.
 *  - Supervisor: every intern placed at their office, with rendered
 *    hours and each one's coordinator. Can approve or reject entries,
 *    record a missed scan manually (as a pending entry), and export or
 *    report on their office's interns.
 *
 * Hour totals keep counting every entry regardless of review status —
 * review is oversight, not a gate on recorded time.
 */
class MonitorController extends Controller
{
    public function index(Request $request)
    {
        $user = $request->user();

        // Every column header sorts by hours progress — click any header
        // to toggle ascending/descending by completion percentage.
        $dir = $request->get('dir') === 'desc' ? 'desc' : 'asc';

        // `accumulated_hours` is computed (sum of the current set's logs),
        // not a column — sort through a correlated subquery that mirrors
        // exactly what the accessor computes.
        $hoursExpr = '(select coalesce(sum(l.hours_rendered), 0) from ojt_logs l'
            .' where l.user_id = users.id'
            .' and l.ojt_enrollment_id = (select id from ojt_enrollments e where e.user_id = users.id order by e.created_at desc, e.id desc limit 1))';

        $interns = $this->scopedInterns($user)
            ->with(['office', 'coordinator', 'currentEnrollment'])
            ->orderByRaw($hoursExpr.' / nullif(users.target_hours, 0) '.$dir)
            ->paginate(30)
            ->withQueryString();

        $collection = $interns->getCollection();

        $stats = [
            'interns' => $interns->total(),
            'active' => $collection->where('is_active', true)->count(),
            'completed' => $collection->filter(fn ($i) => $i->is_complete)->count(),
            'hours' => (float) round($collection->sum('accumulated_hours'), 1),
            'pendingReview' => OjtLog::pendingReview()
                ->whereIn('user_id', $this->scopedInterns($user)->select('id'))
                ->count(),
        ];

        // --- Today board: live duty state ---
        $todayLogs = OjtLog::with(['user.office'])->whereDate('date', today())->get();

        // On-duty: anyone in today's logs with AM clock-in but no PM
        // clock-out yet — INCLUDING interns who already hit their hours
        // (they still punch; the board shows everyone on the clock).
        // Each arrival is marked Late / On-time against the intern's own
        // office window.
        $onDuty = $todayLogs
            ->filter(fn ($l) => $l->am_time_in && ! $l->pm_time_out)
            ->sortBy('am_time_in')
            ->values()
            ->each(fn ($l) => $l->arrival_status = $l->am_time_in > \App\Support\HoursCalculator::arrivalBoundary($l->user->office, \App\Models\OjtSetting::current()) ? 'late' : 'on-time');

        // The boards between them, computed over the FULL scope (the
        // table below paginates; the boards must not): top/bottom 5
        // among ongoing interns with hours, 75%+ near completion, and
        // everyone who has hit their target hours.
        $scopedAll = $this->scopedInterns($user)
            ->with(['office:id,name,type', 'coordinator:id,first_name,last_name', 'currentEnrollment'])
            ->get();

        $withHours = $scopedAll->filter(fn ($i) => $i->accumulated_hours > 0 && ! $i->is_complete);
        $topHighest = $withHours->sortByDesc('accumulated_hours')->take(5)->values();
        $topLeast = $withHours->sortBy('accumulated_hours')->take(5)->values();

        $nearCompletion = $scopedAll
            ->filter(fn ($i) => ! $i->is_complete && $i->completion_percentage >= 75)
            ->sortByDesc('completion_percentage')
            ->take(5)
            ->values();

        $completedList = $scopedAll
            ->filter(fn ($intern) => $intern->is_complete)
            ->sortByDesc('accumulated_hours')
            ->values();

        // Absent: ongoing interns in scope with no scan at all today.
        $settings = \App\Models\OjtSetting::current();
        $workingDay = $settings->isWorkingDay(today());
        $scannedToday = $todayLogs->pluck('user_id')->flip();
        $absent = $workingDay
            ? $scopedAll
                ->filter(fn ($i) => $i->is_active && ! $i->is_complete && ! $scannedToday->has($i->id))
                ->sortBy(fn ($i) => [$i->last_name, $i->first_name])
                ->values()
            : collect();

        return view('monitor.dashboard', [
            'role' => $user->role,
            'office' => $user->office,
            'interns' => $interns,
            'stats' => $stats,
            'completedList' => $completedList,
            'dir' => $dir,
            'onDuty' => $onDuty,
            'topHighest' => $topHighest,
            'topLeast' => $topLeast,
            'nearCompletion' => $nearCompletion,
            'absent' => $absent,
            'workingDay' => $workingDay,
        ]);
    }

    /**
     * One intern's full duty history — visible only to their own
     * coordinator or the supervisor of their office. Supervisors review
     * entries here (approve/reject); coordinators flag them.
     */
    public function show(Request $request, User $intern)
    {
        abort_unless($intern->isIntern(), 404);

        $viewer = $request->user();

        abort_unless($viewer->can('monitor', $intern), 403, 'This intern is not assigned to you.');

        $logs = $intern->currentEnrollment
            ? $intern->currentEnrollment->logs()->with('reviewedBy')->orderByDesc('date')->paginate(25)->withQueryString()
            : $intern->ojtLogs()->with('reviewedBy')->orderByDesc('date')->paginate(25)->withQueryString();

        return view('monitor.intern', [
            'intern' => $intern,
            'logs' => $logs,
            'certifications' => app(\App\Support\TimesheetCertifier::class)->certificationsFor($intern),
            // The Documents tab: every requirement submission this intern
            // has made, newest first — the review panel groups by type.
            'submissions' => \App\Models\SubmittedDocument::where('user_id', $intern->id)
                ->with('reviewer:id,first_name,last_name')
                ->latest()
                ->get(),
        ]);
    }

    /**
     * The supervisor's "missing entry" form — record a scan an intern
     * forgot, for an intern at their office. Saves as a pending entry
     * for the admin to confirm.
     */
    public function createLog(Request $request)
    {
        $interns = $this->scopedInterns($request->user())
            ->where('is_active', true)
            ->orderBy('last_name')
            ->orderBy('first_name')
            ->get();

        return view('monitor.logs.create', ['interns' => $interns]);
    }

    /**
     * Save the supervisor's manual entry — pending review, with the
     * admins notified so it gets confirmed.
     */
    public function storeLog(ManualLogRequest $request, LogReview $logReview)
    {
        $validated = $request->validated();

        $intern = User::findOrFail($validated['user_id']);

        abort_unless($intern->isIntern(), 404);
        abort_unless($request->user()->can('createLog', $intern), 403, 'This intern is not placed at your office.');

        $enrollment = $intern->currentEnrollment;

        if (! $enrollment || $enrollment->status === 'completed') {
            return back()->withErrors(['user_id' => "{$intern->full_name} doesn't have an active OJT set to log hours against."])->withInput();
        }

        $log = $logReview->submitManualEntry($request->user(), $validated);

        $message = "Logged {$log->hours_rendered}h for {$intern->full_name} on {$log->date->format('M d, Y')}"
            .' — saved as pending for the System Admin to confirm.';

        return redirect()->route('monitor.intern', $intern)->with('status', $message);
    }

    /**
     * A review decision on a duty entry: supervisors approve or reject
     * (with a reason), coordinators flag for a second look. The action
     * authorization lives in ReviewLogRequest via the log policies.
     */
    public function review(ReviewLogRequest $request, OjtLog $log, LogReview $logReview)
    {
        $action = $request->validated('action');
        $comment = $request->validated('comment');
        $reviewer = $request->user();

        match ($action) {
            'approve' => $logReview->approve($log, $reviewer, $comment),
            'reject' => $logReview->reject($log, $reviewer, $comment),
            'flag' => $logReview->flag($log, $reviewer, $comment),
        };

        $intern = $log->user;

        $message = match ($action) {
            'approve' => "Approved {$intern->full_name}'s entry for {$log->date->format('M d, Y')}.",
            'reject' => "Rejected {$intern->full_name}'s entry for {$log->date->format('M d, Y')} — the intern has been notified.",
            'flag' => "Flagged {$intern->full_name}'s entry for {$log->date->format('M d, Y')} — the office supervisors have been notified.",
        };

        return redirect()->route('monitor.intern', $intern)->with('status', $message);
    }

    /**
     * CSV export of the viewer's scoped interns' entries over a date
     * range (defaults to the current month) — the monitor counterpart of
     * the admin logbook export, including each entry's review status.
     */
    public function export(Request $request)
    {
        $from = $request->filled('from') ? Carbon::parse($request->get('from'))->startOfDay() : today()->startOfMonth();
        $to = $request->filled('to') ? Carbon::parse($request->get('to'))->startOfDay() : today();

        if ($to->lt($from)) {
            [$from, $to] = [$to, $from];
        }

        $logs = OjtLog::with('user')
            ->whereIn('user_id', $this->scopedInterns($request->user())->select('id'))
            ->whereBetween('date', [$from->toDateString(), $to->toDateString()])
            ->get()
            ->sortBy(fn ($log) => [$log->date->format('Y-m-d'), $log->user->last_name, $log->user->first_name])
            ->values();

        $filename = 'ojt-logbook_'.$from->format('Ymd').'-'.$to->format('Ymd').'.csv';

        return response()->streamDownload(function () use ($logs) {
            $out = fopen('php://output', 'w');

            fputcsv($out, ['Date', 'Intern', 'Student ID', 'AM In', 'AM Out', 'AM In (2)', 'AM Out (2)', 'PM In', 'PM Out', 'PM In (2)', 'PM Out (2)', 'Regular Hours', 'Overtime Hours', 'Total Hours', 'Notes', 'Review Status']);

            foreach ($logs as $log) {
                fputcsv($out, [
                    $log->date->format('Y-m-d'),
                    $log->user->full_name,
                    $log->user->student_id,
                    substr((string) $log->am_time_in, 0, 5),
                    substr((string) $log->am_time_out, 0, 5),
                    substr((string) $log->am_time_in_2, 0, 5),
                    substr((string) $log->am_time_out_2, 0, 5),
                    substr((string) $log->pm_time_in, 0, 5),
                    substr((string) $log->pm_time_out, 0, 5),
                    substr((string) $log->pm_time_in_2, 0, 5),
                    substr((string) $log->pm_time_out_2, 0, 5),
                    $log->regular_hours,
                    $log->overtime_hours,
                    $log->hours_rendered,
                    $log->notes,
                    $log->review_status['label'],
                ]);
            }

            fclose($out);
        }, $filename, ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    /**
     * One intern's Timesheet — the admin's Word template filled with the
     * intern's duty days and hour totals, downloadable by their own
     * coordinator or office supervisor.
     */
    public function timesheet(Request $request, User $intern, TimesheetReportService $service)
    {
        abort_unless($intern->isIntern(), 404);
        abort_unless($request->user()->can('monitor', $intern), 403, 'This intern is not assigned to you.');

        $document = $service->render($intern);

        if (! $document) {
            return redirect()
                ->route('monitor.intern', $intern)
                ->with('status', "This intern's Time Frame isn't available yet — the System Admin hasn't set up the Time Frame template.");
        }

        return $this->streamDocument($document, $request->boolean('download'));
    }

    /**
     * One intern's Weekly Progress Report — their journal entries
     * compiled into the school's weekly form, one page per week.
     */
    public function weeklyReport(Request $request, User $intern, WeeklyProgressReportService $service)
    {
        abort_unless($intern->isIntern(), 404);
        abort_unless($request->user()->can('monitor', $intern), 403, 'This intern is not assigned to you.');

        $document = $service->render($intern);

        if (! $document) {
            return redirect()
                ->route('monitor.intern', $intern)
                ->with('status', "This intern's Weekly Progress Report isn't available yet — the System Admin hasn't set up the Weekly Progress Report template.");
        }

        return $this->streamDocument($document, $request->boolean('download'));
    }

    /**
     * A supervisor's formal per-period (month) sign-off on the intern's
     * duty hours — the record backs the "Certified" state and fills the
     * certified_* merge keys in the Word timesheet. Certifying the same
     * period twice returns the existing record with a notice instead of
     * failing.
     */
    public function certify(\App\Http\Requests\CertifyTimesheetRequest $request, User $intern, \App\Support\TimesheetCertifier $certifier)
    {
        abort_unless($intern->isIntern(), 404);

        $month = $request->filled('month')
            ? \Illuminate\Support\Carbon::createFromFormat('Y-m', $request->get('month'))->startOfMonth()
            : now()->startOfMonth();

        [$certification, $created] = $certifier->certify($request->user(), $intern, $month);

        $message = $created
            ? "Certified {$intern->full_name}'s hours for {$certification->period_label}. The timesheet now carries your sign-off."
            : "{$intern->full_name}'s hours for {$certification->period_label} were already certified"
                .($certification->supervisor?->full_name ? " by {$certification->supervisor->full_name}" : '').'.';

        return redirect()->route('monitor.intern', $intern)->with('status', $message);
    }

    /**
     * File a formal report against an intern — the paper trail for a
     * suspected proxy scan ("the QR was scanned but it wasn't them"), a
     * fabricated entry, or duty conduct. Any staff member who monitors
     * the intern may file one; the System Admin gets it in their inbox.
     */
    public function reportIntern(Request $request, User $intern)
    {
        abort_unless($intern->isIntern(), 404);

        $staff = $request->user();

        abort_unless($staff->isAdmin() || $staff->monitors($intern), 403, 'You can only report interns you monitor.');

        $validated = $request->validate([
            'reason' => ['required', 'string', 'max:1000'],
        ]);

        $report = \App\Models\InternReport::create([
            'intern_id' => $intern->id,
            'reporter_id' => $staff->id,
            'reason' => $validated['reason'],
        ]);

        foreach (User::where('role', 'admin')->where('is_active', true)->whereKeyNot($staff->id)->get() as $admin) {
            $admin->notify(new \App\Notifications\InternReported($report, $intern->full_name, $staff->full_name));
        }

        return redirect()->back()->with('status', "Report filed on {$intern->full_name} — the System Admin has been notified.");
    }

    /**
     * The interns this monitor-role account may see: the UNION of every
     * held role's scope — a coordinator's assigned interns plus their
     * office's when one is set, a supervisor's (or dean's) office
     * placements, a Program Chair's college. A coordinator-supervisor
     * sees both sets.
     */
    private function scopedInterns(User $user)
    {
        if ($user->isChair()) {
            return User::where('role', 'intern')->forStaff($user);
        }

        return User::where('role', 'intern')->where(function ($q) use ($user) {
            $matched = false;

            if ($user->hasRole('coordinator')) {
                $q->orWhere('coordinator_id', $user->id);
                $matched = true;
            }

            if (($user->hasRole('supervisor') || $user->hasRole('dean')) && $user->office_id !== null) {
                $q->orWhere('office_id', $user->office_id);
                $matched = true;
            }

            if (! $matched) {
                $q->whereRaw('0 = 1');
            }
        });
    }
}
