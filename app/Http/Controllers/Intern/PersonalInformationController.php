<?php

namespace App\Http\Controllers\Intern;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * The intern's own Personal Information — the birth details, addresses,
 * contacts, and family background behind the school's "Student Intern's
 * Personal Information" requirement form. Entered once by the intern, then
 * merged per-intern into that .docx instead of one student's data being
 * baked into the template.
 */
class PersonalInformationController extends Controller
{
    /**
     * Show the personal information form, pre-filled with anything saved.
     */
    public function edit(Request $request): View
    {
        return view('intern.personal-information', [
            'intern' => $request->user(),
            'info' => $request->user()->personalInfo,
        ]);
    }

    /**
     * Save the intern's personal information — creating the row on first save
     * and updating it thereafter. Every field is optional so the sheet can be
     * completed a little at a time; blanks print as "N/A" on the form.
     */
    public function update(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'middle_name' => ['nullable', 'string', 'max:255'],
            'birthdate' => ['nullable', 'date', 'before:today'],
            'sex' => ['nullable', 'string', 'max:255'],
            'height' => ['nullable', 'string', 'max:255'],
            'weight' => ['nullable', 'string', 'max:255'],
            'complexion' => ['nullable', 'string', 'max:255'],
            'disability' => ['nullable', 'string', 'max:255'],
            'birth_place' => ['nullable', 'string', 'max:255'],
            'citizenship' => ['nullable', 'string', 'max:255'],
            'civil_status' => ['nullable', 'string', 'max:255'],
            'present_address' => ['nullable', 'string', 'max:500'],
            'present_contact' => ['nullable', 'string', 'max:255'],
            'permanent_address' => ['nullable', 'string', 'max:500'],
            'permanent_contact' => ['nullable', 'string', 'max:255'],
            'father_name' => ['nullable', 'string', 'max:255'],
            'father_occupation' => ['nullable', 'string', 'max:255'],
            'mother_name' => ['nullable', 'string', 'max:255'],
            'mother_occupation' => ['nullable', 'string', 'max:255'],
            'parents_address' => ['nullable', 'string', 'max:500'],
            'parents_contact' => ['nullable', 'string', 'max:255'],
            'guardian_name' => ['nullable', 'string', 'max:255'],
            'guardian_contact' => ['nullable', 'string', 'max:255'],
        ]);

        // The hasOne relation scopes the match to this intern, so an empty
        // match array finds their single row (or creates it with user_id set).
        $request->user()->personalInfo()->updateOrCreate([], $validated);

        return redirect()->route('intern.personal-information.edit')
            ->with('status', 'Personal information saved.');
    }
}
