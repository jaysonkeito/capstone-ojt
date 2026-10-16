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

        // The Completed board: everyone who has hit their target hours,
        // heaviest finishers first.
        $completedList = $completedInterns->sortByDesc('accumulated_hours')->values();

        // --- Today board: live duty state --------------------------------
        $settings = OjtSetting::current();
        $todayLogs = OjtLog::with(['user.office'])
            ->whereDate('date', today())
            ->whereIn('user_id', User::where('role', 'intern')->forStaff(auth()->user())->select('id'))
            ->get();

        // Started the day but hasn't clocked out for good yet — INCLUDING
        // interns who already hit their hours (they still punch; the board
        // shows everyone on the clock). Each arrival is marked Late /
        // On-time against the intern's own office window (campus when
        // unplaced).
        $onDuty = $todayLogs
            ->filter(fn ($l) => $l->am_time_in && ! $l->pm_time_out)
            ->sortBy('am_time_in')
            ->values()
            ->each(fn ($l) => $l->arrival_status = $l->am_time_in > \App\Support\HoursCalculator::arrivalBoundary($l->user->office, $settings) ? 'late' : 'on-time');

        $doneToday = $todayLogs->filter(fn ($l) => $l->pm_time_out)->count();

        $lateToday = $todayLogs
            ->filter(fn ($l) => $l->am_time_in && $l->am_time_in > \App\Support\HoursCalculator::arrivalBoundary($l->user->office, $settings))
            ->values();

        // Top/bottom 5 by hours rendered — only ongoing interns who have
        // actually logged at least 1 hour so the lists are meaningful.
        $withHours = $activeInterns->filter(fn ($i) => $i->accumulated_hours > 0 && ! $i->is_complete);
        $topHighest = $withHours->sortByDesc('accumulated_hours')->take(5)->values();
        $topLeast = $withHours->sortBy('accumulated_hours')->take(5)->values();

        // The Near-completion board: 75%+ and not finished yet.
        $nearCompletion = $interns
            ->filter(fn ($i) => ! $i->is_complete && $i->completion_percentage >= 75)
            ->sortByDesc('completion_percentage')
            ->take(5)
            ->values();

        return view('admin.dashboard', compact(
            'stats',
            'internProgress',
            'completedList',
            'onDuty',
            'doneToday',
            'lateToday',
            'topHighest',
            'topLeast',
            'nearCompletion',
        ));
    }
}
