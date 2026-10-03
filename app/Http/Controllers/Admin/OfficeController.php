<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Office;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

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

    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255', 'unique:offices,name'],
            'type' => ['required', Rule::in(['internal', 'external'])],
            'address' => ['nullable', 'string', 'max:255'],
            'contact_person' => ['nullable', 'string', 'max:255'],
            'contact_email' => ['nullable', 'email', 'max:255'],
            'contact_phone' => ['nullable', 'string', 'max:30'],
        ]);

        Office::create($validated);

        return redirect()->route('admin.offices.index')->with('status', "Office \"{$validated['name']}\" created.");
    }

    public function edit(Office $office)
    {
        return view('admin.offices.form', compact('office'));
    }

    public function update(Request $request, Office $office)
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255', Rule::unique('offices', 'name')->ignore($office->id)],
            'type' => ['required', Rule::in(['internal', 'external'])],
            'address' => ['nullable', 'string', 'max:255'],
            'contact_person' => ['nullable', 'string', 'max:255'],
            'contact_email' => ['nullable', 'email', 'max:255'],
            'contact_phone' => ['nullable', 'string', 'max:30'],
        ]);

        $office->update($validated);

        return redirect()->route('admin.offices.index')->with('status', "Office \"{$validated['name']}\" updated.");
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
