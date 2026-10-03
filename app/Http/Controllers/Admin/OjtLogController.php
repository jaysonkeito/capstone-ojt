<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\OjtLog;
use App\Models\OjtSetting;
use App\Models\User;
use App\Support\LogTimes;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

class OjtLogController extends Controller
{
    /**
     * The "Logbook" page: a date navigator and the list of entries logged
     * for that date. Day-to-day time in/out now happens by QR scan — this
     * is where the admin reviews them and manually fixes or fills in
     * entries when needed.
     */
    public function index(Request $request)
    {
        $date = $request->filled('date')
            ? Carbon::parse($request->get('date'))
            : now()->startOfDay();

        $sort = $request->get('sort'); // 'hours_asc' | 'hours_desc' | null (default: alphabetical by name)
        $status = $request->get('status'); // 'pending' | 'approved' | 'rejected' | null (all)

        $logsQuery = OjtLog::whereDate('date', $date->format('Y-m-d'))
            ->with('user', 'reviewedBy')
            ->whereHas('user', fn ($q) => $q->where('role', 'intern'))
            ->forStaff($request->user())
            ->status(in_array($status, ['pending', 'approved', 'rejected'], true) ? $status : null);

        match ($sort) {
            'hours_asc' => $logsQuery->orderBy('hours_rendered', 'asc'),
            'hours_desc' => $logsQuery->orderBy('hours_rendered', 'desc'),
            // Default: alphabetical by the intern's name (Last, First), no
            // matter who scanned or was entered first. Join users so the sort
            // can key off their name; select only the log columns so the join
            // doesn't overwrite the model's own attributes.
            default => $logsQuery
                ->join('users', 'users.id', '=', 'ojt_logs.user_id')
                ->orderBy('users.last_name')
                ->orderBy('users.first_name')
                ->select('ojt_logs.*'),
        };

        $logs = $logsQuery->get();

        // The New Entry picker only offers interns without an entry for the
        // viewed date — once a day is logged (by scan or by hand) the intern
        // is off the list, matching the date-unique rule in validateLog.
        $interns = $request->user()->visibleInterns()->where('is_active', true)
            ->whereDoesntHave('ojtLogs', fn ($q) => $q->whereDate('date', $date->format('Y-m-d')))
            ->orderBy('last_name')
            ->get();

        return view('admin.logs.index', [
            'interns' => $interns,
            'logs' => $logs,
            'date' => $date,
            'sort' => $sort,
            'status' => $status,
            'prevDate' => $date->copy()->subDay()->format('Y-m-d'),
            'nextDate' => $date->copy()->addDay()->format('Y-m-d'),
            'isToday' => $date->isToday(),
        ]);
    }

    /**
     * Manually create an entry for an intern (e.g. they forgot to scan).
     */
    public function store(Request $request)
    {
        $validated = $this->validateLog($request);

        $intern = User::findOrFail($validated['user_id']);
        abort_unless($intern->isIntern(), 422, 'Hours can only be logged for intern accounts.');
        abort_unless($request->user()->mayAccess($intern), 404);

        $enrollment = $intern->currentEnrollment;

        if (! $enrollment || $enrollment->status === 'completed') {
            return back()->withErrors(['user_id' => "{$intern->full_name} doesn't have an active OJT set to log hours against."])->withInput();
        }

        $log = OjtLog::create([
            ...$validated,
            'ojt_enrollment_id' => $enrollment->id,
            'logged_by' => $request->user()->id,
        ]);

        $message = "Logged {$log->hours_rendered}h for {$intern->full_name} on {$log->date->format('M d, Y')}";
        $message .= $log->has_overtime ? " ({$log->regular_hours}h regular + {$log->overtime_hours}h OT)." : ' (regular hours).';

        return redirect()->route('admin.logs.index', ['date' => $validated['date']])->with('status', $message);
    }

    /**
     * Export a date range of the logbook as CSV — for encoding into the
     * Registrar's records at the end of the term. Defaults to the current
     * month when no range is given.
     */
    public function export(Request $request)
    {
        $from = $request->filled('from') ? Carbon::parse($request->get('from'))->startOfDay() : today()->startOfMonth();
        $to = $request->filled('to') ? Carbon::parse($request->get('to'))->startOfDay() : today();

        if ($to->lt($from)) {
            [$from, $to] = [$to, $from];
        }

        $logs = OjtLog::with('user')
            ->whereBetween('date', [$from->toDateString(), $to->toDateString()])
            ->whereHas('user', fn ($q) => $q->where('role', 'intern'))
            ->forStaff($request->user())
            ->get()
            ->sortBy(fn ($log) => [$log->date->format('Y-m-d'), $log->user->last_name, $log->user->first_name])
            ->values();

        $filename = "ojt-logbook_{$from->format('Ymd')}-{$to->format('Ymd')}.csv";

        return response()->streamDownload(function () use ($logs) {
            $out = fopen('php://output', 'w');

            fputcsv($out, ['Date', 'Intern', 'Student ID', 'AM In', 'AM Out', 'AM In (2)', 'AM Out (2)', 'PM In', 'PM Out', 'PM In (2)', 'PM Out (2)', 'Regular Hours', 'Overtime Hours', 'Total Hours', 'Notes']);

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
                ]);
            }

            fclose($out);
        }, $filename, ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    public function show(Request $request, User $intern)
    {
        abort_unless($intern->isIntern(), 404);

        $sort = $request->get('sort'); // 'hours_asc' | 'hours_desc' | null (default: newest date first)

        $query = $intern->ojtLogs();

        match ($sort) {
            'hours_asc' => $query->orderBy('hours_rendered', 'asc'),
            'hours_desc' => $query->orderBy('hours_rendered', 'desc'),
            default => $query->orderBy('date'),
        };

        $logs = $query->paginate(20)->withQueryString();

        return view('admin.logs.show', compact('intern', 'logs', 'sort'));
    }

    /**
     * Update an existing entry's times/notes (edit, from the Logbook table).
     * The intern and date stay fixed — only the clocked times and notes change.
     */
    public function update(Request $request, OjtLog $log)
    {
        abort_unless($request->user()->mayAccess($log->user), 404);

        $validator = Validator::make($request->all(), [
            'am_time_in' => ['nullable', 'date_format:H:i'],
            'am_time_out' => ['nullable', 'date_format:H:i'],
            'am_time_in_2' => ['nullable', 'date_format:H:i'],
            'am_time_out_2' => ['nullable', 'date_format:H:i'],
            'pm_time_in' => ['nullable', 'date_format:H:i'],
            'pm_time_out' => ['nullable', 'date_format:H:i'],
            'pm_time_in_2' => ['nullable', 'date_format:H:i'],
            'pm_time_out_2' => ['nullable', 'date_format:H:i'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ]);

        $this->validateTimes($validator, $request);

        $validated = $validator->validate();

        $log->fill($validated)->save();

        return back()->with('status', "Updated {$log->user->full_name}'s entry for {$log->date->format('M d, Y')}.");
    }

    public function destroy(Request $request, OjtLog $log)
    {
        abort_unless($request->user()->mayAccess($log->user), 404);

        $intern = $log->user;
        $log->delete();

        return back()->with('status', "Removed log entry for {$intern->full_name}.");
    }

    protected function validateLog(Request $request): array
    {
        $validator = Validator::make($request->all(), [
            'user_id' => ['required', 'exists:users,id'],
            'date' => [
                'required',
                'date',
                'before_or_equal:today',
                Rule::unique('ojt_logs', 'date')->where('user_id', $request->user_id),
            ],
            ...LogTimes::rules(),
            'notes' => ['nullable', 'string', 'max:2000'],
        ], [
            'date.unique' => 'This intern already has a logged entry for that date.',
        ]);

        $this->validateTimes($validator, $request);

        return $validator->validate();
    }

    /**
     * Shared time-field checks for manual create/edit — delegated to
     * LogTimes, which also backs the supervisor's missing-entry form.
     */
    protected function validateTimes(\Illuminate\Contracts\Validation\Validator $validator, Request $request): void
    {
        $validator->after(function ($validator) use ($request) {
            LogTimes::validate($validator, $request->all());
        });
    }
}
