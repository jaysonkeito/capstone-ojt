<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\KioskSetting;
use App\Models\NoClassDay;
use App\Models\OjtSetting;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

class SettingsController extends Controller
{
    /**
     * Show the Settings page: standard working hours, working days,
     * and the no-class calendar for the requested month.
     */
    public function edit(Request $request)
    {
        $settings = OjtSetting::current();

        $month = $request->filled('month')
            ? Carbon::createFromFormat('Y-m', $request->get('month'))->startOfMonth()
            : now()->startOfMonth();

        $noClassDays = NoClassDay::whereBetween('date', [
            $month->copy()->startOfMonth(),
            $month->copy()->endOfMonth(),
        ])->get()->keyBy(fn ($d) => $d->date->format('Y-m-d'));

        $calendarStart = $month->copy()->startOfMonth()->startOfWeek(Carbon::SUNDAY);
        $calendarEnd = $month->copy()->endOfMonth()->endOfWeek(Carbon::SATURDAY);

        $days = [];
        $cursor = $calendarStart->copy();
        while ($cursor->lte($calendarEnd)) {
            $key = $cursor->format('Y-m-d');
            $days[] = [
                'date' => $cursor->copy(),
                'in_month' => $cursor->month === $month->month,
                'no_class' => $noClassDays->get($key),
            ];
            $cursor->addDay();
        }
        $weeks = array_chunk($days, 7);

        return view('admin.settings', [
            'settings' => $settings,
            'month' => $month,
            'weeks' => $weeks,
            'noClassCount' => $noClassDays->count(),
            'prevMonth' => $month->copy()->subMonth()->format('Y-m'),
            'nextMonth' => $month->copy()->addMonth()->format('Y-m'),
            // The campus-default kiosk tab locks this page's form edits —
            // the station-side control lives on Settings, not the scanner.
            'locks' => KioskSetting::locksFor(null),
        ]);
    }

    /**
     * Update standard working hours, grace period, absence start date,
     * and which weekdays count as working days.
     */
    public function update(Request $request)
    {
        $validated = $request->validate([
            'am_time_in' => ['required', 'date_format:H:i'],
            'am_time_out' => ['required', 'date_format:H:i', 'after:am_time_in'],
            'pm_time_in' => ['required', 'date_format:H:i'],
            'pm_time_out' => ['required', 'date_format:H:i', 'after:pm_time_in'],
            'grace_period_minutes' => ['required', 'integer', 'min:0', 'max:120'],
            'absence_start_date' => ['nullable', 'date'],
            'training_starts_on' => ['nullable', 'date'],
            'working_days' => ['array'],
            'working_days.*' => ['integer', 'min:0', 'max:6'],
            'college_name' => ['required', 'string', 'max:120'],
            'college_dean' => ['required', 'string', 'max:120'],
            'college_code' => ['required', 'string', 'max:20', 'regex:/^[a-z0-9_-]+$/'],
            'campus_name' => ['required', 'string', 'max:160'],
        ]);

        $validated['working_days'] = $validated['working_days'] ?? [];

        OjtSetting::current()->update($validated);

        return back()->with('status', 'Standard working hours updated. This now applies to automatic overtime detection for every intern.');
    }
}
