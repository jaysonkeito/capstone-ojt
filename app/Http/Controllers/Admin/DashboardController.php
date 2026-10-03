<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\OjtLog;
use App\Models\OjtSetting;
use App\Models\User;
use Illuminate\Support\Carbon;

class DashboardController extends Controller
{
    /**
     * The staff dashboard — the same surface for the System Admin and the
     * two monitoring roles, scoped to what each may see: admins get every
     * intern; coordinators their assigned interns; supervisors the interns
     * placed at their office (the On-Duty board included).
     */
    public function index()
    {
        $interns = User::where('role', 'intern')
            ->forStaff(auth()->user())
            ->get();

        $activeInterns = $interns->where('is_active', true);
        $completedInterns = $interns->filter(fn ($i) => $i->is_complete);

        $stats = [
            'total_interns' => $interns->count(),
            'active_interns' => $activeInterns->count(),
            'completed_interns' => $completedInterns->count(),
            'total_hours_logged' => (float) round($interns->sum('accumulated_hours'), 2),
            'pending_interns' => $interns->where('ojt_status', 'pending')->count(),
            'average_hours' => $activeInterns->count() > 0
                ? round($activeInterns->sum('accumulated_hours') / $activeInterns->count(), 1)
                : 0,
            'average_progress' => $activeInterns->count() > 0
                ? round($activeInterns->avg('completion_percentage'), 1)
                : 0,
        ];

        // Interns sorted by completion percentage (highest first)
        $internProgress = $interns->sortByDesc('completion_percentage')->values();

        // --- Today board: live duty state --------------------------------
        $settings = OjtSetting::current();
        $todayLogs = OjtLog::with('user')
            ->whereDate('date', today())
            ->whereIn('user_id', User::where('role', 'intern')->forStaff(auth()->user())->select('id'))
            ->get();

        $graceEnd = Carbon::parse($settings->am_time_in)
            ->addMinutes($settings->grace_period_minutes)
            ->format('H:i:s');

        // Started the day but hasn't clocked out for good yet.
        $onDuty = $todayLogs
            ->filter(fn ($l) => $l->am_time_in && ! $l->pm_time_out)
            ->sortBy('am_time_in')
            ->values();

        $doneToday = $todayLogs->filter(fn ($l) => $l->pm_time_out)->count();

        $lateToday = $todayLogs
            ->filter(fn ($l) => $l->am_time_in && $l->am_time_in > $graceEnd)
            ->values();

        // Active interns with no entry at all today (working days only).
        $noScanYet = $settings->isWorkingDay(today())
            ? max($stats['active_interns'] - $todayLogs->count(), 0)
            : null;

        // Top/bottom 3 by hours rendered — only interns who have
        // actually logged at least 1 hour so the lists are meaningful.
        $withHours = $activeInterns->filter(fn ($i) => $i->accumulated_hours > 0);
        $top3Highest = $withHours->sortByDesc('accumulated_hours')->take(3)->values();
        $top3Least = $withHours->sortBy('accumulated_hours')->take(3)->values();

        return view('admin.dashboard', compact(
            'stats',
            'internProgress',
            'onDuty',
            'doneToday',
            'lateToday',
            'noScanYet',
            'top3Highest',
            'top3Least',
        ));
    }
}
