<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class LoginController extends Controller
{
    /**
     * Show the login form.
     */
    public function create()
    {
        return view('auth.login');
    }

    /**
     * Handle an authentication attempt.
     *
     * Accepts either an email address or a student ID in the `login` field.
     * Admin accounts must log in with their email; interns may use either
     * their student ID or their email.
     */
    public function store(Request $request)
    {
        // Throttle by login name + IP: 5 failed attempts locks the pair
        // out for a minute, with the countdown shown in the form errors.
        $throttleKey = Str::transliterate(Str::lower($request->string('login')).'|'.$request->ip());

        if (RateLimiter::tooManyAttempts($throttleKey, 5)) {
            $seconds = RateLimiter::availableIn($throttleKey);

            throw ValidationException::withMessages([
                'login' => "Too many login attempts. Try again in {$seconds} second".($seconds === 1 ? '' : 's').'.',
            ]);
        }

        $credentials = $request->validate([
            'login' => ['required', 'string'],
            'password' => ['required', 'string'],
        ]);

        $loginField = filter_var($credentials['login'], FILTER_VALIDATE_EMAIL)
            ? 'email'
            : 'student_id';

        $user = User::where($loginField, $credentials['login'])->first();

        if (! $user || ! $user->is_active) {
            RateLimiter::hit($throttleKey);

            throw ValidationException::withMessages([
                'login' => 'These credentials do not match our records, or the account is inactive.',
            ]);
        }

        // Default password policy is "last name" (interns and staff alike).
        // Bcrypt hashes are case-sensitive, so a submitted password that
        // case-insensitively matches this user's actual last name gets
        // normalized to the stored casing before the real auth check —
        // this makes login case-insensitive without needing to touch any
        // already-hashed passwords in the database.
        $passwordToCheck = $credentials['password'];
        if ($user->last_name && strcasecmp($passwordToCheck, $user->last_name) === 0) {
            $passwordToCheck = $user->last_name;
        }

        if (! Auth::attempt(
            [$loginField => $credentials['login'], 'password' => $passwordToCheck],
            $request->boolean('remember')
        )) {
            RateLimiter::hit($throttleKey);

            throw ValidationException::withMessages([
                'login' => 'These credentials do not match our records.',
            ]);
        }

        RateLimiter::clear($throttleKey);

        $request->session()->regenerate();

        return redirect()->intended($this->homeRoute($user));
    }

    /**
     * Each role's landing page after login. Accounts that signed up
     * themselves and never completed their profile land on the profile
     * completion page instead of a dashboard — the gate lifts the moment
     * they save the form.
     */
    protected function homeRoute(User $user): string
    {
        if ($user->isAdmin()) {
            return route('admin.dashboard');
        }

        if ($user->needsProfileCompletion()) {
            return route('profile-completion.edit');
        }

        if ($user->isDean()) {
            // A dean who also coordinates a program lands on their interns;
            // otherwise straight to the approvals desk.
            return App\Models\User::where('coordinator_id', $user->id)->exists()
                ? route('monitor.dashboard')
                : route('admin.approvals.index');
        }

        if ($user->isMonitor()) {
            return route('monitor.dashboard');
        }

        return route('intern.dashboard');
    }

    /**
     * Log the user out.
     */
    public function destroy(Request $request)
    {
        Auth::guard('web')->logout();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('login');
    }
}
