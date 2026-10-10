<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Office;
use App\Models\OjtEnrollment;
use App\Models\OjtSetting;
use App\Models\User;
use App\Services\DailyReportService;
use App\Services\TimesheetReportService;
use App\Support\OjtEnrollmentService;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Symfony\Component\HttpFoundation\HeaderUtils;

class InternController extends Controller
{
    public function __construct(private OjtEnrollmentService $enrollments) {}

    /**
     * List all interns with the cross-cutting filter set:
     * search, OJT status, track, department, batch.
     */
    public function index(Request $request)
    {
        $sort = $request->get('sort', 'name');
        // Hours defaults to descending (highest first) unless explicitly set
        $defaultDir = $sort === 'hours' ? 'desc' : 'asc';
        $dir = $request->get('dir') === 'asc' ? 'asc' : ($request->get('dir') === 'desc' ? 'desc' : $defaultDir);

        // `accumulated_hours` is computed (sum of the current set's logs),
        // not a column — sort through a correlated subquery that mirrors
        // exactly what the accessor computes.
        $hoursExpr = '(select coalesce(sum(l.hours_rendered), 0) from ojt_logs l'
            .' where l.user_id = users.id'
            .' and l.ojt_enrollment_id = (select id from ojt_enrollments e where e.user_id = users.id order by e.created_at desc, e.id desc limit 1))';

        $query = User::where('role', 'intern')
            ->forStaff($request->user())
            ->with(['currentEnrollment', 'office', 'coordinator'])
            ->search($request->get('search'))
            ->ojtStatus($request->get('status'))
            ->track($request->get('track'));

        // Sorting by office or coordinator needs eager-loaded relations,
        // so we fetch first and sort in-memory to avoid ambiguous joins.
        if (in_array($sort, ['office', 'coordinator'])) {
            $interns = $query->get();

            $interns = $interns->sortBy(
                fn ($intern) => $sort === 'office'
                    ? ($intern->office?->name ?? 'zzz')
                    : ($intern->coordinator?->last_name ?? 'zzz'),
                SORT_REGULAR,
                $dir === 'desc',
            )->values();

            // Manual slice-based pagination
            $page = (int) $request->get('page', 1);
            $perPage = 20;
            $paged = $interns->slice(($page - 1) * $perPage, $perPage)->values();

            $interns = new LengthAwarePaginator(
                $paged,
                $interns->count(),
                $perPage,
                $page,
                ['path' => $request->url(), 'query' => $request->query()],
            );
        } else {
            match ($sort) {
                'name' => $query->orderBy('last_name', $dir)->orderBy('first_name', $dir),
                'student_id' => $query->orderBy('student_id', $dir),
                'hours' => $query->orderByRaw($hoursExpr.' / nullif(users.target_hours, 0) '.$dir),
                'status' => $query->orderBy('ojt_status', $dir),
                default => $query->orderBy('last_name', $dir)->orderBy('first_name', $dir),
            };

            $interns = $query->paginate(20)->withQueryString();
        }

        // Live search fetches just the table fragment (no page chrome) so
        // results refresh without reloading — the input keeps focus.
        if ($request->boolean('partial')) {
            return view('admin.interns.partials.list', compact('interns', 'sort', 'dir'));
        }

        return view('admin.interns.index', [
            'interns' => $interns,
            'sort' => $sort,
            'dir' => $dir,
            'offices' => Office::orderBy('name')->get(),
            'coordinators' => User::where('role', 'coordinator')->orderBy('last_name')->get(),
            'defaultTrainingStart' => $this->defaultTrainingStart(),
        ]);
    }

    /**
     * Show the form to create a new intern account.
     */
    public function create()
    {
        return view('admin.interns.create', [
            ...$this->assignmentOptions(),
            'defaultTrainingStart' => $this->defaultTrainingStart(),
        ]);
    }

    /**
     * Store a newly created intern — and their first OJT set.
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'student_id' => ['required', 'string', 'max:20', 'unique:users,student_id'],
            'first_name' => ['required', 'string', 'max:255'],
            'last_name' => ['required', 'string', 'max:255'],
            'email' => ['nullable', 'email', 'max:255', 'unique:users,email'],
            'ojt_track' => ['required', Rule::in(['internship', 'custom'])],
            'custom_label' => ['required_if:ojt_track,custom', 'nullable', 'string', 'max:255'],
            'target_hours' => ['required', 'integer', 'min:1', 'max:5000'],
            'ojt_status' => ['required', Rule::in(['pending', 'active', 'completed'])],
            'department' => ['nullable', 'string', 'max:20'],
            'year_level' => ['nullable', 'integer', 'in:1,2,3,4'],
            'batch' => ['nullable', 'string', 'max:255'],
            'training_starts_on' => ['nullable', 'date'],
            'password' => ['nullable', 'string', 'min:6'],
            'office_id' => ['nullable', 'exists:offices,id'],
            'coordinator_id' => ['nullable', Rule::exists('users', 'id')->where('role', 'coordinator')],
        ]);

        $email = $validated['email'] ?: "{$validated['student_id']}@norsubscojt.online";
        $label = $this->labelFor($validated['ojt_track'], $validated['custom_label'] ?? null);
        // Optional on the form — absent from API-style requests entirely.
        $initialPassword = ($validated['password'] ?? null) ?: null;

        // Monitors create interns inside their own scope: a supervisor's
        // hires go to their office, a coordinator's to their list.
        $staff = $request->user();
        $officeId = $validated['office_id'] ?? null;
        $coordinatorId = $validated['coordinator_id'] ?? null;
        if ($staff->isSupervisor()) {
            $officeId = $staff->office_id;
        } elseif ($staff->isDean() && $staff->office_id) {
            // A dean supervising their office's interns creates them there.
            $officeId = $staff->office_id;
            $coordinatorId = $staff->id;
        } elseif ($staff->isCoordinator()) {
            $coordinatorId = $staff->id;
        }

        $intern = User::create([
            'role' => 'intern',
            'student_id' => $validated['student_id'],
            'first_name' => $validated['first_name'],
            'last_name' => $validated['last_name'],
            'email' => $email,
            // Default password policy: the intern's last name — unless a
            // custom initial password was supplied on the form. A custom
            // password counts as "already changed"; the default prompts
            // the intern to pick their own on first login.
            'password' => Hash::make($initialPassword ?: $validated['last_name']),
            'password_changed_at' => $initialPassword ? now() : null,
            'ojt_track' => $validated['ojt_track'],
            'ojt_status' => $validated['ojt_status'],
            'department' => ($validated['department'] ?? null) ?: null,
            'year_level' => $validated['year_level'] ?? null,
            'batch' => ($validated['batch'] ?? null) ?: null,
            'office_id' => $officeId,
            'coordinator_id' => $coordinatorId,
            'target_hours' => $validated['target_hours'],
            'is_active' => true,
            // Admin-provisioned accounts skip the first-login profile
            // completion wall — the admin already filled their record.
            'profile_completed_at' => now(),
        ]);

        OjtEnrollment::create([
            'user_id' => $intern->id,
            'label' => $label,
            'target_hours' => $validated['target_hours'],
            'status' => $validated['ojt_status'],
            'started_at' => $validated['training_starts_on'] ?? now()->toDateString(),
        ]);

        $status = $initialPassword
            ? 'Intern account created with the custom password you set.'
            : 'Intern account created. Default password is their last name.';

        return redirect()->route('admin.interns.index')->with('status', $status);
    }

    /**
     * Show the form to edit an intern (target hours, active status, etc).
     */
    public function edit(User $intern)
    {
        abort_unless($intern->isIntern() && request()->user()->mayAccess($intern), 404);

        return view('admin.interns.edit', [
            'intern' => $intern,
            ...$this->assignmentOptions(),
        ]);
    }

    /**
     * Office placement + coordinator assignment options, shared by the
     * create and edit forms.
     *
     * @return array{offices: Collection<Office>, coordinators: Collection<User>}
     */
    private function assignmentOptions(): array
    {
        return [
            'offices' => Office::orderBy('name')->get(),
            'coordinators' => User::where('role', 'coordinator')->orderBy('last_name')->get(),
        ];
    }

    /**
     * The date new intern forms are pre-filled with — the OJT period start
     * configured in Settings, or today when none is set yet.
     */
    private function defaultTrainingStart(): string
    {
        return OjtSetting::current()->training_starts_on?->format('Y-m-d')
            ?? now()->toDateString();
    }

    /**
     * The intern's complete record — a tabbed full view: profile details,
     * duty history, the timesheet as it will print, and the monthly
     * summary PDF, all on one page (tabs switch client-side).
     */
    public function show(User $intern)
    {
        abort_unless($intern->isIntern() && request()->user()->mayAccess($intern), 404);

        $intern->load([
            'office',
            'coordinator',
            'enrollments.logs',
            'personalInfo',
        ]);

        // Duty History tab — every entry of the current set, newest first,
        // with the review state in view.
        $currentEnrollment = $intern->currentEnrollment;

        $logs = $currentEnrollment
            ? $currentEnrollment->logs()->with('reviewedBy')->orderByDesc('date')->get()
            : collect();

        // Timesheet tab — the same rows the Word timesheet prints (oldest
        // first), plus the set's hour totals and the supervisor's latest
        // per-period certification, so the document can be judged on-screen
        // before it's downloaded.
        $timesheetLogs = $logs->sortBy('date')->values();

        $timesheetRows = $timesheetLogs->isNotEmpty()
            ? app(\App\Services\TimesheetReportService::class)->rows($timesheetLogs)
            : [];

        $timesheetTotals = [
            'days' => $timesheetLogs->count(),
            'regular' => (float) $timesheetLogs->sum('regular_hours'),
            'overtime' => (float) $timesheetLogs->sum('overtime_hours'),
            'total' => (float) $timesheetLogs->sum('hours_rendered'),
        ];

        $certification = \App\Models\TimesheetCertification::query()
            ->where('intern_id', $intern->id)
            ->when($currentEnrollment, fn ($q) => $q->where('ojt_enrollment_id', $currentEnrollment->id))
            ->with('supervisor')
            ->latest('certified_at')
            ->first();

        return view('admin.interns.show', [
            'intern' => $intern,
            'logs' => $logs,
            'timesheetRows' => $timesheetRows,
            'timesheetTotals' => $timesheetTotals,
            'certification' => $certification,
            // Documents tab — the intern's requirement submissions with
            // their review states (the admin decides like the coordinator).
            'submissions' => \App\Models\SubmittedDocument::where('user_id', $intern->id)
                ->with('reviewer:id,first_name,last_name')
                ->latest()
                ->get(),
        ]);
    }

    /**
     * Update an intern's profile — edits their CURRENT OJT set in place.
     * To move them onto a brand-new set (e.g. Summer -> Internship) once
     * this one is done, use startNewSet() instead.
     */
    public function update(Request $request, User $intern)
    {
        abort_unless($intern->isIntern() && request()->user()->mayAccess($intern), 404);

        $validated = $request->validate([
            'student_id' => ['required', 'string', 'max:20', Rule::unique('users', 'student_id')->ignore($intern->id)],
            'first_name' => ['required', 'string', 'max:255'],
            'last_name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', Rule::unique('users', 'email')->ignore($intern->id)],
            'ojt_track' => ['required', Rule::in(['internship', 'custom'])],
            'custom_label' => ['required_if:ojt_track,custom', 'nullable', 'string', 'max:255'],
            'target_hours' => ['required', 'integer', 'min:1', 'max:5000'],
            'ojt_status' => ['required', Rule::in(['pending', 'active', 'completed'])],
            'department' => ['nullable', 'string', 'max:20'],
            'year_level' => ['nullable', 'integer', 'in:1,2,3,4'],
            'batch' => ['nullable', 'string', 'max:255'],
            'training_starts_on' => ['nullable', 'date'],
            'is_active' => ['sometimes', 'boolean'],
            'office_id' => ['nullable', 'exists:offices,id'],
            'coordinator_id' => ['nullable', Rule::exists('users', 'id')->where('role', 'coordinator')],
        ]);

        $intern->update([
            'student_id' => $validated['student_id'],
            'first_name' => $validated['first_name'],
            'last_name' => $validated['last_name'],
            'email' => $validated['email'],
            'department' => $validated['department'] ?? null,
            'year_level' => $validated['year_level'] ?? null,
            'batch' => $validated['batch'] ?? null,
            'office_id' => $validated['office_id'] ?? null,
            'coordinator_id' => $validated['coordinator_id'] ?? null,
            'is_active' => $request->boolean('is_active'),
        ]);

        $setAttributes = [
            'label' => $this->labelFor($validated['ojt_track'], $validated['custom_label'] ?? null),
            'target_hours' => $validated['target_hours'],
            'status' => $validated['ojt_status'],
        ];

        // Only touch the start date when the admin supplied one — a cleared
        // field leaves the enrollment's existing date untouched.
        if ($request->filled('training_starts_on')) {
            $setAttributes['started_at'] = $validated['training_starts_on'];
        }

        $this->enrollments->updateCurrentSet($intern, $setAttributes);

        return redirect()->route('admin.interns.index')
            ->with('status', "Updated {$intern->full_name}'s record.");
    }

    /**
     * Start a brand-new OJT set for this intern (e.g. Summer -> Internship)
     * once their current set is marked Completed. Resets hours back to
     * zero on purpose.
     */
    public function startNewSet(Request $request, User $intern)
    {
        abort_unless($intern->isIntern() && request()->user()->mayAccess($intern), 404);

        $validated = $request->validate([
            'ojt_track' => ['required', Rule::in(['internship', 'custom'])],
            'custom_label' => ['required_if:ojt_track,custom', 'nullable', 'string', 'max:255'],
            'target_hours' => ['required', 'integer', 'min:1', 'max:5000'],
        ]);

        $label = $this->labelFor($validated['ojt_track'], $validated['custom_label'] ?? null);

        $this->enrollments->startNewSet($intern, $label, $validated['target_hours']);

        return redirect()->route('admin.interns.index')
            ->with('status', "Started a new \"{$label}\" set for {$intern->full_name} ({$validated['target_hours']}h), active and ready to log hours.");
    }

    /**
     * Reset an intern's password back to their last name.
     */
    public function resetPassword(User $intern)
    {
        abort_unless($intern->isIntern() && request()->user()->mayAccess($intern), 404);

        $intern->update([
            'password' => Hash::make($intern->last_name),
            // Back to the default password → the intern will be prompted
            // to pick their own again on their next login.
            'password_changed_at' => null,
        ]);

        return back()->with('status', "Password reset to \"{$intern->last_name}\" for {$intern->full_name}. They'll be asked to set a new one on next login.");
    }

    /**
     * Monthly hours summary for one intern — a print-ready PDF with every
     * duty day of the month, hour totals, and OJT-set progress. The
     * paperwork half of the end-of-term requirements.
     */
    public function summary(Request $request, User $intern)
    {
        abort_unless($intern->isIntern() && request()->user()->mayAccess($intern), 404);

        $month = $request->filled('month') && preg_match('/^\d{4}-\d{2}$/', (string) $request->get('month'))
            ? Carbon::createFromFormat('Y-m', $request->get('month'))->startOfMonth()
            : now()->startOfMonth();

        $logs = $intern->ojtLogs()
            ->whereBetween('date', [$month->toDateString(), $month->copy()->endOfMonth()->toDateString()])
            ->orderBy('date')
            ->get();

        $totals = [
            'days' => $logs->count(),
            'regular' => (float) $logs->sum('regular_hours'),
            'overtime' => (float) $logs->sum('overtime_hours'),
            'total' => (float) $logs->sum('hours_rendered'),
        ];

        $html = view('admin.interns.summary', [
            'intern' => $intern,
            'month' => $month,
            'logs' => $logs,
            'totals' => $totals,
            'generatedAt' => now(),
        ])->render();

        $pdf = Pdf::loadHTML($html)->setPaper('a4', 'portrait');
        $filename = "MonthlySummary_{$intern->student_id}_{$month->format('Y-m')}.pdf";

        return $request->boolean('download')
            ? $pdf->download($filename)
            : $pdf->stream($filename);
    }

    /**
     * The intern's duty journals compiled into one PDF — one page per day,
     * the exact same Daily Report layout as the single-journal export — for
     * the Daily Journal tab's export options:
     *
     *  - daily  ?date=YYYY-MM-DD      one specific day
     *  - weekly ?week_of=YYYY-MM-DD   that day's Monday–Sunday week
     *  - range  ?from=&to=            an inclusive date range
     *  - all                          every journal on record
     */
    public function exportJournals(Request $request, User $intern)
    {
        abort_unless($intern->isIntern() && request()->user()->mayAccess($intern), 404);

        $type = $request->input('type', 'all');

        $query = $intern->ojtLogs()->with(['user', 'loggedBy', 'ojtEnrollment'])->orderBy('date');
        $label = 'all';

        if ($type === 'daily') {
            $request->validate(['date' => ['required', 'date', 'before_or_equal:today']]);
            $query->whereDate('date', $request->input('date'));
            $label = Carbon::parse((string) $request->input('date'))->format('Y-m-d');
        } elseif ($type === 'weekly') {
            $request->validate(['week_of' => ['required', 'date']]);
            $start = Carbon::parse((string) $request->input('week_of'))->startOfWeek(Carbon::MONDAY);
            $end = $start->copy()->endOfWeek(Carbon::SUNDAY);
            $query->whereBetween('date', [$start->toDateString(), $end->toDateString()]);
            $label = 'week-of-'.$start->format('Y-m-d');
        } elseif ($type === 'range') {
            $request->validate([
                'from' => ['required', 'date'],
                'to' => ['required', 'date', 'after_or_equal:from'],
            ]);
            $from = Carbon::parse((string) $request->input('from'))->startOfDay();
            $to = Carbon::parse((string) $request->input('to'))->startOfDay();
            $query->whereBetween('date', [$from->toDateString(), $to->toDateString()]);
            $label = $from->format('Y-m-d').'_to_'.$to->format('Y-m-d');
        }

        $logs = $query->get();

        if ($logs->isEmpty()) {
            return back()->with('status', 'No duty journals found for that selection.');
        }

        $binary = (new DailyReportService)->binaryForLogs($logs);

        $namePart = Str::of("{$intern->last_name} {$intern->first_name}")
            ->replaceMatches('/[^A-Za-z0-9]+/', '_')
            ->trim('_');
        $filename = "Journals_{$namePart}_{$label}.pdf";

        return new Response($binary, 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => HeaderUtils::makeDisposition('attachment', $filename, $filename),
            'Content-Length' => strlen($binary),
        ]);
    }

    /**
     * The intern's Timesheet — every duty day of their CURRENT OJT set filled
     * into the admin's uploaded Word template (the very document the intern
     * downloads themselves). Redirects back to the intern's logbook with a
     * notice when no Timesheet template has been uploaded yet.
     */
    public function timesheet(Request $request, User $intern, TimesheetReportService $service): Response|RedirectResponse
    {
        abort_unless($intern->isIntern() && request()->user()->mayAccess($intern), 404);

        $document = $service->render($intern);

        if (! $document) {
            return redirect()
                ->route('admin.logs.show', $intern)
                ->with('status', 'No Time Frame template uploaded yet. Add one under Templates to generate this Time Frame.');
        }

        return $this->streamDocument($document, $request->boolean('download'));
    }

    /**
     * Soft-delete an intern: hidden from the list and can't log in, but their
     * logs, enrollments, and personal details survive (the cascade only fires
     * on a hard delete), so restoring brings everything back.
     */
    public function destroy(User $intern)
    {
        abort_unless($intern->isIntern() && request()->user()->mayAccess($intern), 404);

        $intern->update(['is_active' => false]); // logs out any live session
        $intern->delete();

        return redirect()->route('admin.interns.index')->with('status', "{$intern->full_name} deleted — restore anytime from the Deleted section below.");
    }

    public function restore($internId)
    {
        $intern = User::onlyTrashed()->where('role', 'intern')->findOrFail($internId);
        abort_unless(request()->user()->mayAccess($intern), 404);

        $intern->restore();
        $intern->update(['is_active' => true]);

        return redirect()->route('admin.interns.index')->with('status', "{$intern->full_name} restored and reactivated.");
    }

    /**
     * Permanently remove an archived (soft-deleted) intern — no undo. Only
     * accounts already sitting in the Deleted section qualify. Their logs,
     * enrollments, and personal details are cascaded away with the record.
     */
    public function forceDelete($internId)
    {
        $intern = User::onlyTrashed()->where('role', 'intern')->findOrFail($internId);
        abort_unless(request()->user()->mayAccess($intern), 404);

        $name = $intern->full_name;

        $intern->forceDelete();

        return redirect()->route('admin.interns.index')->with('status', "{$name} permanently deleted.");
    }

    private function labelFor(string $track, ?string $customLabel): string
    {
        return match ($track) {
            'internship' => 'Internship OJT',
            default => $customLabel ?: 'Custom OJT',
        };
    }
}
