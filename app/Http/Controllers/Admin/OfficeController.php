<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\KioskSetting;
use App\Models\OjtSetting;
use App\Models\Office;
use App\Support\HoursCalculator;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class OfficeController extends Controller
{
    /**
     * All offices — internal (on-campus) and external (partner
     * companies/organizations) — with who is placed at each.
     */
    public function index()
    {
        $offices = Office::withCount(['interns', 'supervisors'])
            ->orderBy('name')
            ->get();

        return view('admin.offices.index', compact('offices'));
    }

    public function create()
    {
        return view('admin.offices.form', ['office' => new Office]);
    }

    /**
     * One office's full record: placement details, contact, the working
     * times its interns' hours are measured against, kiosk tab locks,
     * and everyone placed here.
     */
    public function show(Office $office)
    {
        $office->loadCount(['interns', 'supervisors']);

        $interns = $office->interns()
            ->with(['coordinator:id,first_name,last_name', 'currentEnrollment'])
            ->orderBy('last_name')
            ->orderBy('first_name')
            ->get();

        $supervisors = $office->supervisors()
            ->orderBy('last_name')
            ->orderBy('first_name')
            ->get();

        // Effective working times (office overrides where set) next to the
        // campus defaults, so the page can mark which slots are overridden.
        $campus = OjtSetting::current();
        $hours = HoursCalculator::hoursFor($office);

        $format = fn (?string $time) => $time
            ? \Illuminate\Support\Carbon::parse($time)->format('g:i A')
            : null;

        $slots = [
            ['label' => 'AM In', 'office' => $office->am_time_in, 'campus' => $campus->am_time_in, 'effective' => $format($hours['am_start'])],
            ['label' => 'AM Out', 'office' => $office->am_time_out, 'campus' => $campus->am_time_out, 'effective' => $format($hours['am_end'])],
            ['label' => 'PM In', 'office' => $office->pm_time_in, 'campus' => $campus->pm_time_in, 'effective' => $format($hours['pm_start'])],
            ['label' => 'PM Out', 'office' => $office->pm_time_out, 'campus' => $campus->pm_time_out, 'effective' => $format($hours['pm_end'])],
        ];

        return view('admin.offices.show', [
            'office' => $office,
            'interns' => $interns,
            'supervisors' => $supervisors,
            'slots' => $slots,
            'locks' => KioskSetting::locksFor($office->id),
        ]);
    }

    public function store(Request $request)
    {
        $validated = $this->validatedOfficeData($request);

        Office::create($validated);

        return redirect()->route('admin.offices.index')->with('status', "Office \"{$validated['name']}\" created.");
    }

    public function edit(Office $office)
    {
        return view('admin.offices.form', compact('office'));
    }

    public function update(Request $request, Office $office)
    {
        $validated = $this->validatedOfficeData($request, $office->id);

        $office->update($validated);

        return redirect()->route('admin.offices.index')->with('status', "Office \"{$validated['name']}\" updated.");
    }

    /**
     * The office form's validated fields, shared by create and update.
     * Working times are optional per-office overrides of the campus
     * schedule — blank inputs become true nulls ("use campus times"),
     * and each session's window must run forward.
     */
    private function validatedOfficeData(Request $request, ?int $uniqueIgnore = null): array
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255', Rule::unique('offices', 'name')->ignore($uniqueIgnore)],
            'type' => ['required', Rule::in(['internal', 'external'])],
            'address' => ['nullable', 'string', 'max:255'],
            'agency' => ['nullable', 'string', 'max:160'],
            'city' => ['nullable', 'string', 'max:120'],
            'province' => ['nullable', 'string', 'max:120'],
            'postal' => ['nullable', 'string', 'max:20'],
            'contact_person' => ['nullable', 'string', 'max:255'],
            'contact_email' => ['nullable', 'email', 'max:255'],
            'contact_phone' => ['nullable', 'string', 'max:30'],
            'am_time_in' => ['nullable', 'date_format:H:i'],
            'am_time_out' => ['nullable', 'date_format:H:i'],
            'pm_time_in' => ['nullable', 'date_format:H:i'],
            'pm_time_out' => ['nullable', 'date_format:H:i'],
        ]);

        foreach (['am_time_in', 'am_time_out', 'pm_time_in', 'pm_time_out'] as $field) {
            $validated[$field] = ($validated[$field] ?? null) ?: null;
        }

        foreach ([['am_time_in', 'am_time_out'], ['pm_time_in', 'pm_time_out']] as [$in, $out]) {
            if ($validated[$in] && $validated[$out] && $validated[$out] <= $validated[$in]) {
                throw ValidationException::withMessages([
                    $out => ucfirst(str_replace('_', ' ', $out)).' must be after '.str_replace('_', ' ', $in).'.',
                ]);
            }
        }

        return $validated;
    }

    /**
     * Deleting an office un-places its interns and supervisors (the FK
     * nulls out); their accounts and records stay intact.
     */
    public function destroy(Office $office)
    {
        $name = $office->name;
        $office->delete();

        return redirect()->route('admin.offices.index')->with('status', "Office \"{$name}\" deleted. Everyone who was placed there is now unassigned.");
    }
}
