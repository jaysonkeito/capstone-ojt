<?php

namespace App\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/**
 * Profile Completion — the first-login wall every self-service account
 * clears before reaching a dashboard. Interns fill the student sheet
 * (birth details, guardian, contact, links) from the reference mockup;
 * coordinators and supervisors fill their instructor record. Saving the
 * form once stamps `profile_completed_at` on the user, which is what the
 * EnsureProfileCompleted middleware and the login redirects check.
 *
 * The page stays reachable afterwards (pre-filled) so the same details can
 * be kept up to date — re-saving never re-triggers the first-login gate.
 */
class ProfileCompletionController extends Controller
{
    /**
     * Programs a self-registered intern can claim on the completion form.
     * The Registrar's roster spells the old IT code "BSINT"; the display
     * maps that to the canonical BSIT code.
     */
    public const INTERN_PROGRAMS = [
        'BSIT' => 'Bachelor of Science in Information Technology',
        'BSCS' => 'Bachelor of Science in Computer Science',
    ];

    public const YEAR_LEVELS = [1, 2, 3, 4];

    /**
     * Show the completion form for the signed-in user's role — the student
     * sheet for interns, the instructor sheet for staff — pre-filled with
     * anything already saved.
     */
    public function edit(Request $request): View
    {
        $user = $request->user();

        return view('profile-completion', [
            'user' => $user,
            'info' => $user->personalInfo,
            'profile' => $user->staffProfile,
            'programs' => self::INTERN_PROGRAMS,
            'yearLevels' => self::YEAR_LEVELS,
        ]);
    }

    /**
     * Save the completion form and unlock the account's dashboard. Interns
     * persist their account details onto the users row and their personal
     * details onto their single `intern_personal_infos` row (created on
     * first save); staff persist theirs onto their single `staff_profiles`
     * row. First save redirects to the dashboard; later saves are plain
     * edits and stay on the page.
     */
    public function update(Request $request): RedirectResponse
    {
        $user = $request->user();

        $firstTime = $user->profile_completed_at === null;

        if ($user->isIntern()) {
            $this->saveInternProfile($request);
        } else {
            $this->saveStaffProfile($request);
        }

        // The completion gate checks this stamp; set it only after the
        // details above saved cleanly.
        $user->update(['profile_completed_at' => now()]);

        if ($firstTime) {
            return redirect($user->isIntern() ? route('intern.dashboard') : route('monitor.dashboard'))
                ->with('status', 'Profile complete — welcome aboard!');
        }

        return redirect()->route('profile-completion.edit')
            ->with('status', 'Profile details updated.');
    }

    /**
     * Intern branch: account details onto users, student information onto
     * intern_personal_infos.
     */
    protected function saveInternProfile(Request $request): void
    {
        $user = $request->user();

        $validated = $request->validate([
            'username' => ['nullable', 'string', 'max:50', Rule::unique('users', 'username')->ignore($user->id)],
            'email' => ['required', 'email', 'max:255', Rule::unique('users', 'email')->ignore($user->id)],
            // Program select stores the canonical code (BSINT is normalized
            // to BSIT); year level drives the timesheet's Roman numeral.
            'department' => ['nullable', Rule::in(['BSIT', 'BSCS'])],
            'year_level' => ['nullable', 'integer', Rule::in(self::YEAR_LEVELS)],
            'photo' => ['nullable', 'image', 'mimes:jpeg,png,jpg,webp', 'max:2048'],
            'remove_photo' => ['nullable', 'boolean'],
            'middle_name' => ['nullable', 'string', 'max:255'],
            'birthdate' => ['nullable', 'date', 'before:today'],
            'sex' => ['nullable', 'string', 'max:255'],
            'institutional_email' => ['nullable', 'email', 'max:255'],
            'phone_number' => ['nullable', 'string', 'max:255'],
            'major' => ['nullable', 'string', 'max:255'],
            'guardian_name' => ['nullable', 'string', 'max:255'],
            'guardian_contact' => ['nullable', 'string', 'max:255'],
            'present_address' => ['nullable', 'string', 'max:500'],
            'facebook_link' => ['nullable', 'string', 'max:500'],
            'youtube_link' => ['nullable', 'string', 'max:500'],
            'linkedin_link' => ['nullable', 'string', 'max:500'],
        ], [
            'username.unique' => 'That username is already taken.',
            'department.in' => 'Pick a program from the list.',
            'year_level.in' => 'Pick a year level from the list.',
        ]);

        $user->update([
            'username' => $this->emptyToNull($validated['username'] ?? null),
            'email' => $validated['email'],
            'department' => $this->normalizeProgram($validated['department'] ?? null),
            'year_level' => $validated['year_level'] ?? null,
            'avatar_path' => $this->resolveAvatarPath($request, $user->avatar_path),
        ]);

        $details = array_filter([
            'middle_name' => $validated['middle_name'] ?? null,
            'birthdate' => $validated['birthdate'] ?? null,
            'sex' => $validated['sex'] ?? null,
            'institutional_email' => $validated['institutional_email'] ?? null,
            'phone_number' => $validated['phone_number'] ?? null,
            'major' => $validated['major'] ?? null,
            'guardian_name' => $validated['guardian_name'] ?? null,
            'guardian_contact' => $validated['guardian_contact'] ?? null,
            'present_address' => $validated['present_address'] ?? null,
            'facebook_link' => $validated['facebook_link'] ?? null,
            'youtube_link' => $validated['youtube_link'] ?? null,
            'linkedin_link' => $validated['linkedin_link'] ?? null,
        ], fn ($value) => $value !== null);

        // The hasOne relation scopes the match to this intern, so an empty
        // match array finds their single row (or creates it with user_id set).
        if ($details !== []) {
            $user->personalInfo()->updateOrCreate([], $details);
        }
    }

    /**
     * Staff branch: account details onto users, instructor information onto
     * staff_profiles.
     */
    protected function saveStaffProfile(Request $request): void
    {
        $user = $request->user();

        $validated = $request->validate([
            'username' => ['nullable', 'string', 'max:50', Rule::unique('users', 'username')->ignore($user->id)],
            'first_name' => ['required', 'string', 'max:255'],
            'last_name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', Rule::unique('users', 'email')->ignore($user->id)],
            'prefix_title' => ['nullable', 'string', 'max:50'],
            'suffix_title' => ['nullable', 'string', 'max:50'],
            'middle_name' => ['nullable', 'string', 'max:255'],
            'employee_id' => ['nullable', 'string', 'max:50'],
            'institutional_email' => ['nullable', 'email', 'max:255'],
            'civil_status' => ['nullable', 'string', 'max:255'],
            'designation' => ['nullable', 'string', 'max:255'],
            'date_hired' => ['nullable', 'date'],
            'department' => ['nullable', 'string', 'max:255'],
            'gender' => ['nullable', 'string', 'max:255'],
            'mobile_number' => ['nullable', 'string', 'max:30'],
            'qualification' => ['nullable', 'string', 'max:255'],
            'specialization' => ['nullable', 'string', 'max:255'],
            'photo' => ['nullable', 'image', 'mimes:jpeg,png,jpg,webp', 'max:2048'],
            'remove_photo' => ['nullable', 'boolean'],
            'resume' => ['nullable', 'file', 'mimes:pdf,doc,docx', 'max:5120'],
        ], [
            'username.unique' => 'That username is already taken.',
        ]);

        $user->update([
            'username' => $this->emptyToNull($validated['username'] ?? null),
            'first_name' => $validated['first_name'],
            'last_name' => $validated['last_name'],
            'email' => $validated['email'],
            'avatar_path' => $this->resolveAvatarPath($request, $user->avatar_path),
        ]);

        $profile = $user->staffProfile()->firstOrNew([]);

        // Resume: replace the stored file when a new one is uploaded, keep
        // the existing one otherwise.
        if ($request->hasFile('resume')) {
            if ($profile->resume_path) {
                Storage::disk('public')->delete($profile->resume_path);
            }
            $profile->resume_path = $request->file('resume')->store("staff-resumes/{$user->id}", 'public');
        }

        $profile->middle_name = $this->emptyToNull($validated['middle_name'] ?? null);
        $profile->prefix_title = $this->emptyToNull($validated['prefix_title'] ?? null);
        $profile->suffix_title = $this->emptyToNull($validated['suffix_title'] ?? null);
        $profile->employee_id = $this->emptyToNull($validated['employee_id'] ?? null);
        $profile->institutional_email = $this->emptyToNull($validated['institutional_email'] ?? null);
        $profile->civil_status = $this->emptyToNull($validated['civil_status'] ?? null);
        $profile->designation = $this->emptyToNull($validated['designation'] ?? null);
        $profile->date_hired = $this->emptyToNull($validated['date_hired'] ?? null);
        $profile->department = $this->emptyToNull($validated['department'] ?? null);
        $profile->gender = $this->emptyToNull($validated['gender'] ?? null);
        $profile->mobile_number = $this->emptyToNull($validated['mobile_number'] ?? null);
        $profile->qualification = $this->emptyToNull($validated['qualification'] ?? null);
        $profile->specialization = $this->emptyToNull($validated['specialization'] ?? null);

        $profile->user_id = $user->id;
        $profile->save();
    }

    /**
     * Handle the photo field shared by both branches: a fresh upload
     * replaces any existing avatar, a "remove photo" flag clears it.
     */
    protected function resolveAvatarPath(Request $request, ?string $currentPath): ?string
    {
        if ($request->hasFile('photo')) {
            if ($currentPath) {
                Storage::disk('public')->delete($currentPath);
            }

            return $request->file('photo')->store("avatars/{$request->user()->id}", 'public');
        }

        if ($request->boolean('remove_photo') && $currentPath) {
            Storage::disk('public')->delete($currentPath);

            return null;
        }

        return $currentPath;
    }

    /**
     * Normalize a stored program code to the canonical list (the Registrar
     * spelled the IT course "BSINT"; the self-service picker stores BSIT).
     */
    protected function normalizeProgram(?string $program): ?string
    {
        return match ($program) {
            'BSINT' => 'BSIT',
            null, '' => null,
            default => $program,
        };
    }

    protected function emptyToNull(?string $value): ?string
    {
        return $value === null || trim($value) === '' ? null : $value;
    }
}
