<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
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
     * Accepts an email address, a Student ID, or a staff username in the
     * `login` field. Interns use their Student ID or email; staff accounts
     * pick a username at sign-up, so it must work as a login handle too.
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

        $login = $credentials['login'];

        // Resolve the account across the three login handles. Digits resolve
        // to the intern's Student ID before any staff username, matching the
        // form label's order.
        $user = null;
        foreach (['email', 'student_id', 'username'] as $field) {
            $user = User::where($field, $login)->first();
            if ($user) {
                break;
            }
        }

        if (! $user) {
            RateLimiter::hit($throttleKey);

            throw ValidationException::withMessages([
                'login' => 'These credentials do not match our records.',
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

        if (! Hash::check($passwordToCheck, $user->password)) {
            RateLimiter::hit($throttleKey);

            throw ValidationException::withMessages([
                'login' => 'These credentials do not match our records.',
            ]);
        }

        // The password is right, so the account state decides the message:
        // a staff sign-up held for approval gets an explanation instead of a
        // generic failure — this is the first thing they try after applying.
        if (! $user->is_active) {
            RateLimiter::hit($throttleKey);

            throw ValidationException::withMessages([
                'login' => $user->approved_at === null
                    ? 'Your application is still awaiting approval. You\'ll be able to sign in once the System Admin or your College Dean approves your account.'
                    : 'This account has been deactivated. Please contact the System Admin if you believe this is a mistake.',
            ]);
        }

        RateLimiter::clear($throttleKey);

        Auth::login($user, $request->boolean('remember'));

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

        // Office scanner accounts land straight on the station — they exist
        // for the kiosk PC and have no other surface.
        if ($user->isOffice()) {
            return route('admin.kiosk.index');
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
