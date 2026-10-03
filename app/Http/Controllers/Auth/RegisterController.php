<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Support\OjtEnrollmentService;
use Closure;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

class RegisterController extends Controller
{
    public function __construct(private OjtEnrollmentService $enrollments) {}

    /**
     * Show the self-service registration form.
     */
    public function create()
    {
        return view('auth.register');
    }

    /**
     * Live "is this taken?" check for the sign-up form. Lets the form flag a
     * duplicate Student ID, email, username, or full name inline as the user
     * types, rather than only after submitting.
     *
     * Soft-deleted accounts still count as taken (they can be restored), which
     * mirrors the submit-time unique rules exactly.
     */
    public function availability(Request $request): JsonResponse
    {
        $data = $request->validate([
            'field' => ['required', Rule::in(['student_id', 'email', 'username', 'full_name'])],
            'value' => ['nullable', 'string', 'max:255'],
            'first_name' => ['nullable', 'string', 'max:255'],
            'last_name' => ['nullable', 'string', 'max:255'],
        ]);

        $value = trim((string) ($data['value'] ?? ''));

        $taken = match ($data['field']) {
            'student_id' => $value !== '' && User::withTrashed()->where('student_id', $value)->exists(),
            'email' => $value !== '' && User::withTrashed()->where('email', $value)->exists(),
            'username' => $value !== '' && User::withTrashed()->where('username', $value)->exists(),
            'full_name' => $this->fullNameExists($data['first_name'] ?? '', $data['last_name'] ?? ''),
        };

        return response()->json(['taken' => $taken]);
    }

    /**
     * Register a new account.
     *
     * Self-registration covers the three non-admin roles (admin accounts are
     * provisioned internally, never self-served). Interns are active right
     * away — the admin fills in the OJT placement details (office,
     * coordinator, target hours) afterwards, and they get their first OJT set
     * so the dashboard has something to read from on day one. Coordinator and
     * supervisor sign-ups are held for the System Admin's approval instead:
     * they stay inactive until an admin approves them under Account Approvals.
     */
    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'account_type' => ['required', Rule::in(['intern', 'coordinator', 'supervisor'])],
            // Student ID identifies interns (and doubles as a login handle);
            // staff accounts don't have one. Digits only — no letters or
            // punctuation — so it stays a clean numeric handle.
            'student_id' => ['nullable', 'required_if:account_type,intern', 'regex:/^\d+$/', 'max:20', 'unique:users,student_id'],
            // Staff pick a username at sign-up (their public handle — interns
            // use their Student ID instead, so they never submit one here).
            'username' => ['nullable', 'required_unless:account_type,intern', 'string', 'max:50', 'unique:users,username'],
            'first_name' => ['required', 'string', 'max:255'],
            // No two accounts may share the same full name. The check lives on
            // last_name so the error surfaces once both name fields are filled.
            'last_name' => ['required', 'string', 'max:255', $this->uniqueFullName($request)],
            'email' => ['required', 'email', 'max:255', 'unique:users,email'],
            // Strong by construction: upper + lower case, a number, and a
            // symbol, at least 8 characters. Mirrored live on the form.
            'password' => ['required', 'confirmed', Password::min(8)->mixedCase()->numbers()->symbols()],
            'agree_terms' => ['accepted'],
        ], [
            'student_id.required_if' => 'Your Student ID is required for an intern account.',
            'student_id.regex' => 'Your Student ID must contain digits only (0–9).',
            'student_id.unique' => 'That Student ID is already registered.',
            'email.unique' => 'That email is already registered — try signing in instead.',
            'username.required_unless' => 'Choose a username for your account.',
            'username.unique' => 'That username is already taken.',
            'agree_terms.accepted' => 'Please accept the Terms of Use to continue.',
        ]);

        $isIntern = $validated['account_type'] === 'intern';

        $user = User::create([
            'role' => $validated['account_type'],
            'student_id' => $isIntern ? $validated['student_id'] : null,
            'username' => $isIntern ? null : $validated['username'],
            'first_name' => $validated['first_name'],
            'last_name' => $validated['last_name'],
            'email' => $validated['email'],
            'password' => Hash::make($validated['password']),
            // Self-chosen password → not the default, so no "change your
            // password" nudge on first login.
            'password_changed_at' => now(),
            // Interns are approved by construction; staff sign-ups stay
            // inactive until the System Admin approves them.
            'is_active' => $isIntern,
            'approved_at' => $isIntern ? now() : null,
        ]);

        // Interns start with their first OJT set (Internship, 500h) so hours,
        // progress, and the logbook all have an enrollment to read through.
        // The service keeps the users mirror columns in sync for us.
        if ($user->isIntern()) {
            $this->enrollments->startNewSet($user, 'Internship OJT', User::TRACK_HOURS['internship']);
        }

        // No auto-login on sign-up — the fresh account proves its own
        // credentials at the sign-in page before reaching a dashboard. Staff
        // accounts can't even sign in yet: their application is under review.
        $status = $isIntern
            ? 'Account created — please sign in to continue.'
            : 'Application submitted — the System Administrator will review it. You\'ll be able to sign in once your account is approved.';

        return redirect()->route('login')->with('status', $status);
    }

    /**
     * A closure rule that fails when another account already uses the same
     * first + last name (case-insensitively). Attached to the last_name field
     * so the message lands next to the name inputs.
     */
    protected function uniqueFullName(Request $request): Closure
    {
        return function (string $attribute, mixed $value, Closure $fail) use ($request): void {
            if ($this->fullNameExists((string) $request->input('first_name', ''), (string) $value)) {
                $fail('An account with this name already exists.');
            }
        };
    }

    /**
     * Whether any account (including soft-deleted ones, which can be restored)
     * already uses this exact first + last name, compared case-insensitively.
     * Empty names never count as taken.
     */
    protected function fullNameExists(string $firstName, string $lastName): bool
    {
        $firstName = trim($firstName);
        $lastName = trim($lastName);

        if ($firstName === '' || $lastName === '') {
            return false;
        }

        return User::withTrashed()
            ->whereRaw('LOWER(first_name) = ?', [mb_strtolower($firstName)])
            ->whereRaw('LOWER(last_name) = ?', [mb_strtolower($lastName)])
            ->exists();
    }
}
