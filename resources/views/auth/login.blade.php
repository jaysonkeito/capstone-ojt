@extends('layouts.auth')

@section('title', 'Sign in')
@section('welcome_side', 'left')

@section('content')
    {{-- Compact brand block — only visible on mobile, where the welcome
         panel is hidden. --}}
    <div class="form-brand">
        <div class="form-brand-icon">N</div>
        <p style="font-weight:600;letter-spacing:-0.01em;margin:0;">NORSU OJT Tracker</p>
        <p style="font-size:.8rem;color:var(--text-muted);margin:.2rem 0 0;">Time in, time out, done with a scan.</p>
    </div>

    <h1 class="auth-heading">Sign in</h1>
    <p class="auth-subheading">to continue to your dashboard</p>

    {{-- Success flashes + sign-in errors appear as auto-dismissing toasts
         (see partials/toast via layouts/auth). Keeping the typed input on
         failure still matters: the clear-fields script below checks for the
         error toast to decide whether to preserve the fields. --}}
    <form method="POST" action="{{ route('login.store') }}" autocomplete="off">
        @csrf

        <div class="field">
            <label for="login" class="field-label">Student ID or Email</label>
            <input type="text" id="login" name="login" class="auth-input" required autofocus
                   value="{{ old('login') }}"
                   autocomplete="off" autocapitalize="off" spellcheck="false"
                   placeholder="202300524">
        </div>

        <div class="field">
            <label for="password" class="field-label">Password</label>
            <div class="password-group">
                <input type="password" id="password" name="password" class="auth-input" required
                       autocomplete="off" placeholder="Interns: your last name">
                <button type="button" class="password-toggle" data-target="password"
                        onclick="togglePassword(this)" aria-label="Show or hide password">
                    @include('partials.password-eye')
                </button>
            </div>
        </div>

        <label class="checkbox-row" style="margin-bottom:1.2rem;">
            <input type="checkbox" name="remember" value="1">
            <span>Remember me on this device</span>
        </label>

        <button type="submit" class="btn-primary">Sign In</button>
    </form>

    <p class="auth-alt">
        New here? <a href="{{ route('register') }}">Create an account</a>
    </p>
@endsection

@push('scripts')
    <script>
        // Keep the login fields empty on every fresh visit — right after
        // signing in (Back button), after logging out, or a direct load — so
        // no stale credentials ever linger in the form. Runs at several
        // points because browsers autofill late (after DOMContentLoaded) and
        // restore values from the bfcache on back-navigation. The one
        // exception is a failed sign-in attempt: the error flash is present,
        // so the typed input stays for an easy retry.
        document.addEventListener('DOMContentLoaded', function () {
            var login = document.getElementById('login');
            var password = document.getElementById('password');
            if (!login || !password) { return; }

            function clearFields() {
                if (document.querySelector('.toast[data-toast-role="error"]')) { return; }
                login.value = '';
                password.value = '';
            }

            clearFields();
            window.addEventListener('load', clearFields);
            window.addEventListener('pageshow', clearFields);
            // Beat any late browser autofill.
            setTimeout(clearFields, 100);
        });
    </script>
@endpush

@section('welcome_panel')
    <div class="welcome-inner">
        <div class="welcome-brand-icon">N</div>
        <h2>Welcome back!</h2>
        <p>Sign in to log your hours, track your OJT progress, and pull your reports — all in one place.</p>
        <p class="welcome-cta-hint">Don't have an account?</p>
        <a href="{{ route('register') }}" class="btn-welcome-cta">Create account</a>
    </div>
@endsection
