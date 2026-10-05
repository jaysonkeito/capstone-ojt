@extends('layouts.auth')

@section('title', 'Create account')
@section('welcome_side', 'right')

@php
    $accountType = old('account_type', 'intern');
    // On a validation error the page reloads — open the earliest step that
    // has an error so the user lands right on the field they need to fix.
    $initialStep = 1;
    if ($errors->hasAny(['account_type'])) {
        $initialStep = 1;
    } elseif ($errors->hasAny(['student_id', 'username', 'first_name', 'last_name', 'email'])) {
        $initialStep = 2;
    } elseif ($errors->hasAny(['password', 'agree_terms'])) {
        $initialStep = 3;
    }
@endphp

@section('content')
    {{-- Compact brand block — only visible on mobile, where the welcome
         panel is hidden. --}}
    <div class="form-brand">
        <div class="form-brand-icon">N</div>
        <p style="font-weight:600;letter-spacing:-0.01em;margin:0;">NORSU OJT Tracker</p>
        <p style="font-size:.8rem;color:var(--text-muted);margin:.2rem 0 0;">Create your account to get started.</p>
    </div>

    <h1 class="auth-heading">Create your account</h1>
    <p class="auth-subheading">Just three quick steps</p>

    {{-- Field-level @error messages render next to their inputs below; the
         full validation summary appears as an auto-dismissing toast from
         partials/toast via layouts/auth. --}}

    {{-- Step indicator. Decorative — the step headings below carry the
         meaning for assistive tech. --}}
    <div class="wizard-progress" aria-hidden="true">
        <div class="wizard-progress-item" data-step="1">
            <span class="wizard-dot">1</span>
            <span class="wizard-label">Role</span>
        </div>
        <div class="wizard-progress-item" data-step="2">
            <span class="wizard-dot">2</span>
            <span class="wizard-label">Your details</span>
        </div>
        <div class="wizard-progress-item" data-step="3">
            <span class="wizard-dot">3</span>
            <span class="wizard-label">Password</span>
        </div>
    </div>

    <form method="POST" action="{{ route('register.store') }}" autocomplete="off"
          id="registerForm" class="wizard" data-initial-step="{{ $initialStep }}">
        @csrf

        {{-- ---- Step 1: Role ---- --}}
        <div class="wizard-step" data-step="1">
            <h2 class="wizard-heading">Choose your role</h2>
            <div class="field" style="margin-bottom:.6rem;">
                <div class="role-toggle">
                    <input type="radio" name="account_type" id="type_intern" value="intern"
                           {{ $accountType === 'intern' ? 'checked' : '' }}>
                    <label for="type_intern">Intern</label>

                    <input type="radio" name="account_type" id="type_coordinator" value="coordinator"
                           {{ $accountType === 'coordinator' ? 'checked' : '' }}>
                    <label for="type_coordinator">Coordinator</label>

                    <input type="radio" name="account_type" id="type_supervisor" value="supervisor"
                           {{ $accountType === 'supervisor' ? 'checked' : '' }}>
                    <label for="type_supervisor">Supervisor</label>
                </div>

                {{-- Staff accounts are reviewed by the System Admin before
                     they can sign in — told up front so applicants aren't
                     surprised when they can't log in right away. --}}
                
            </div>
        </div>

        {{-- ---- Step 2: Your details ---- --}}
        <div class="wizard-step" data-step="2">
            <h2 class="wizard-heading">Your details</h2>

            {{-- Student ID — interns only. Hidden (and cleared) for staff roles. --}}
            <div class="field {{ $accountType === 'intern' ? '' : 'hidden' }} @error('student_id') has-error @enderror" id="studentIdField">
                <label for="student_id" class="field-label">Student ID</label>
                <input type="text" id="student_id" name="student_id" class="auth-input"
                       value="{{ old('student_id') }}"
                       inputmode="numeric" pattern="\d+" maxlength="20"
                       autocomplete="off" autocapitalize="off" spellcheck="false"
                       aria-describedby="err_student_id"
                       placeholder="202300524">
                <span class="field-error" id="err_student_id">@error('student_id'){{ $message }}@enderror</span>
            </div>

            {{-- Username — staff accounts only, sitting above the full name.
                 Interns identify by Student ID instead, so this stays hidden
                 (and cleared) for them. --}}
            <div class="field {{ $accountType === 'intern' ? 'hidden' : '' }}" id="collegeField">
                <label for="college_code" class="field-label">College</label>
                <select id="college_code" name="college_code" class="auth-input">
                    @foreach($colleges ?? [] as $college)
                        <option value="{{ $college->code }}" {{ old('college_code', 'cas') === $college->code ? 'selected' : '' }}>{{ $college->name }}</option>
                    @endforeach
                </select>
                <span class="field-error" id="err_college_code">@error('college_code'){{ $message }}@enderror</span>
            </div>

            <div class="field {{ $accountType === 'intern' ? 'hidden' : '' }} @error('username') has-error @enderror" id="usernameField">
                <label for="username" class="field-label">Username</label>
                <input type="text" id="username" name="username" class="auth-input"
                       value="{{ old('username') }}"
                       maxlength="50"
                       autocomplete="off" autocapitalize="off" spellcheck="false"
                       aria-describedby="err_username"
                       placeholder="e.g. maria_santos">
                <span class="field-error" id="err_username">@error('username'){{ $message }}@enderror</span>
            </div>

            <div style="display:grid;grid-template-columns:1fr 1fr;gap:.75rem;">
                <div class="field @error('first_name') has-error @enderror">
                    <label for="first_name" class="field-label">First name</label>
                    <input type="text" id="first_name" name="first_name" class="auth-input" required
                           value="{{ old('first_name') }}" aria-describedby="err_first_name" placeholder="Juan">
                    <span class="field-error" id="err_first_name">@error('first_name'){{ $message }}@enderror</span>
                </div>
                <div class="field @error('last_name') has-error @enderror">
                    <label for="last_name" class="field-label">Last name</label>
                    <input type="text" id="last_name" name="last_name" class="auth-input" required
                           value="{{ old('last_name') }}" aria-describedby="err_last_name" placeholder="Dela Cruz">
                    <span class="field-error" id="err_last_name">@error('last_name'){{ $message }}@enderror</span>
                </div>
            </div>

            <div class="field @error('email') has-error @enderror">
                <label for="email" class="field-label">Email</label>
                <input type="email" id="email" name="email" class="auth-input" required
                       value="{{ old('email') }}"
                       autocomplete="off" autocapitalize="off" spellcheck="false"
                       aria-describedby="err_email"
                       placeholder="you@example.com">
                <span class="field-error" id="err_email">@error('email'){{ $message }}@enderror</span>
            </div>
        </div>

        {{-- ---- Step 3: Password ---- --}}
        <div class="wizard-step" data-step="3">
            <h2 class="wizard-heading">Set your password</h2>

            <div class="field @error('password') has-error @enderror">
                <div class="field-label-row">
                    <label for="password" class="field-label">Password</label>
                    <button type="button" class="pw-suggest" id="suggestPassword">Suggest strong password</button>
                </div>
                <div class="password-group">
                    <input type="password" id="password" name="password" class="auth-input" required
                           autocomplete="new-password" aria-describedby="err_password pwReqs"
                           placeholder="At least 8 characters">
                    <button type="button" class="password-toggle" data-target="password"
                            onclick="togglePassword(this)" aria-label="Show or hide password">
                        @include('partials.password-eye')
                    </button>
                </div>
                <span class="field-error" id="err_password">@error('password'){{ $message }}@enderror</span>

                {{-- Live requirements — each ticks green the moment the typed
                     password satisfies it. Mirrors the server-side Password rule. --}}
                <ul class="pw-reqs" id="pwReqs" aria-live="polite">
                    <li data-rule="length"><span class="pw-req-mark" aria-hidden="true"></span>At least 8 characters</li>
                    <li data-rule="upper"><span class="pw-req-mark" aria-hidden="true"></span>An uppercase letter (A–Z)</li>
                    <li data-rule="lower"><span class="pw-req-mark" aria-hidden="true"></span>A lowercase letter (a–z)</li>
                    <li data-rule="number"><span class="pw-req-mark" aria-hidden="true"></span>A number (0–9)</li>
                    <li data-rule="symbol"><span class="pw-req-mark" aria-hidden="true"></span>A special character (!&#64;#$…)</li>
                </ul>
            </div>

            <div class="field @error('password_confirmation') has-error @enderror">
                <label for="password_confirmation" class="field-label">Confirm password</label>
                <div class="password-group">
                    <input type="password" id="password_confirmation" name="password_confirmation" class="auth-input" required
                           autocomplete="new-password" aria-describedby="err_password_confirmation"
                           placeholder="Re-enter your password">
                    <button type="button" class="password-toggle" data-target="password_confirmation"
                            onclick="togglePassword(this)" aria-label="Show or hide password">
                        @include('partials.password-eye')
                    </button>
                </div>
                <span class="field-error" id="err_password_confirmation"></span>
            </div>

            <label class="checkbox-row">
                <input type="checkbox" name="agree_terms" value="1" {{ old('agree_terms') ? 'checked' : '' }} required
                       aria-describedby="err_agree_terms">
                <span>I agree to the Terms of Use and Privacy Policy of the NORSU OJT Tracker.</span>
            </label>
            <span class="field-error" id="err_agree_terms" style="margin-top:-.6rem;">@error('agree_terms'){{ $message }}@enderror</span>
        </div>

        {{-- ---- Navigation ---- --}}
        <div class="wizard-nav">
            <button type="button" class="btn-secondary" data-wizard="back">Back</button>
            <button type="button" class="btn-primary" data-wizard="next">Next</button>
            <button type="submit" class="btn-primary" data-wizard="submit">Create account</button>
        </div>
    </form>

    <p class="auth-alt">
        Already have an account? <a href="{{ route('login') }}">Sign in</a>
    </p>
@endsection

@section('welcome_panel')
    <div class="welcome-inner">
        <div class="welcome-brand-icon">N</div>
        <h2>Hello, friend!</h2>
        <p>Set up your NORSU OJT Tracker account to start logging duty hours and keeping your OJT records in order.</p>
        <p class="welcome-cta-hint">Already registered?</p>
        <a href="{{ route('login') }}" class="btn-welcome-cta">Sign in</a>
    </div>
@endsection

@push('styles')
<style>
    .wizard-heading { font-size: 1rem; font-weight: 600; color: var(--ink); margin: 0 0 1rem; }

    /* Step indicator — three numbered dots joined by a track line. */
    .wizard-progress { display: flex; align-items: flex-start; justify-content: space-between; position: relative; margin-bottom: 1.75rem; }
    .wizard-progress::before {
        content: ""; position: absolute; top: 16px; left: 12%; right: 12%; height: 2px;
        background: var(--border-soft); z-index: 0;
    }
    .wizard-progress-item { position: relative; z-index: 1; flex: 1; display: flex; flex-direction: column; align-items: center; gap: .4rem; }
    .wizard-dot {
        width: 34px; height: 34px; border-radius: 50%; background: #fff;
        border: 2px solid var(--border-soft); color: var(--text-muted);
        display: flex; align-items: center; justify-content: center;
        font-size: .85rem; font-weight: 600; transition: all .2s ease;
    }
    .wizard-progress-item.is-active .wizard-dot { border-color: var(--brand-600); color: var(--brand-600); box-shadow: 0 0 0 4px rgba(99,102,241,0.12); }
    .wizard-progress-item.is-done .wizard-dot { background: var(--brand-600); border-color: var(--brand-600); color: #fff; }
    .wizard-label { font-size: .72rem; font-weight: 500; color: var(--text-muted); }
    .wizard-progress-item.is-active .wizard-label,
    .wizard-progress-item.is-done .wizard-label { color: var(--ink); }

    /* Progressive enhancement: without JS every step shows and the form is a
       single long page with one submit button. JS adds .is-enhanced, which
       reveals one step at a time plus the Back/Next controls. */
    .wizard.is-enhanced .wizard-step { display: none; }
    .wizard.is-enhanced .wizard-step.is-current { display: block; animation: wizardFade .25s ease; }
    @keyframes wizardFade { from { opacity: 0; transform: translateY(6px); } to { opacity: 1; transform: none; } }

    .wizard-nav { display: flex; gap: .75rem; margin-top: 1.75rem; }
    .wizard-nav .btn-secondary { flex: 0 0 auto; }
    .wizard-nav .btn-primary { flex: 1; }
    .wizard:not(.is-enhanced) [data-wizard="back"],
    .wizard:not(.is-enhanced) [data-wizard="next"] { display: none; }

    .btn-secondary {
        border: 1px solid var(--border-soft); background: #fff; color: #374151;
        font-size: .92rem; font-weight: 600; padding: .7rem 1.25rem;
        border-radius: 10px; cursor: pointer; transition: all .15s ease;
    }
    .btn-secondary:hover { background: #f9fafb; border-color: #d1d5db; }

    /* Inline field errors: hidden until they hold a message, and the paired
       input turns red when its wrapper is flagged. */
    .field-error:empty { display: none; }
    .field.has-error .auth-input { border-color: #dc2626; }
    .field.has-error .auth-input:focus { box-shadow: 0 0 0 3px rgba(220,38,38,0.15); }

    /* Password label row: label on the left, "suggest" action on the right. */
    .field-label-row { display: flex; align-items: baseline; justify-content: space-between; gap: .5rem; }
    .pw-suggest {
        border: none; background: transparent; padding: 0; cursor: pointer;
        font-size: .76rem; font-weight: 600; color: var(--brand-600);
        margin-bottom: .4rem;
    }
    .pw-suggest:hover { color: var(--brand-700); text-decoration: underline; }

    /* Live password requirements checklist. */
    .pw-reqs { list-style: none; margin: .6rem 0 0; padding: 0; display: grid; gap: .3rem; }    .pw-reqs li { display: flex; align-items: center; gap: .5rem; font-size: .76rem; color: var(--text-muted); transition: color .15s ease; }
    .pw-req-mark {
        flex: 0 0 auto; width: 15px; height: 15px; border-radius: 50%;
        border: 1.5px solid var(--border-soft); background: #fff;
        display: inline-flex; align-items: center; justify-content: center;
        position: relative; transition: all .15s ease;
    }
    .pw-reqs li.ok { color: #047857; }
    .pw-reqs li.ok .pw-req-mark { background: #10b981; border-color: #10b981; }
    .pw-reqs li.ok .pw-req-mark::after {
        content: ""; width: 4px; height: 8px; margin-top: -1px;
        border: solid #fff; border-width: 0 2px 2px 0; transform: rotate(45deg);
    }

    @media (prefers-reduced-motion: reduce) {
        .wizard.is-enhanced .wizard-step.is-current { animation: none; }
    }
</style>
@endpush

@push('scripts')
<script>
    document.addEventListener('DOMContentLoaded', function () {
        var form = document.getElementById('registerForm');
        if (!form) { return; }

        // --- Inline (live) field errors -----------------------------------
        // One helper drives every inline message: set text + flag the wrapper
        // red, or clear both when passed an empty string.
        function setFieldError(id, message) {
            var span = document.getElementById('err_' + id);
            var input = document.getElementById(id);
            if (span) { span.textContent = message || ''; }
            if (input) {
                var field = input.closest('.field');
                if (field) { field.classList.toggle('has-error', !!message); }
            }
        }

        // --- Role selection ------------------------------------------------
        // Student ID is for interns only: show + require it for interns; hide,
        // un-require, and clear it for staff roles. Username mirrors that in
        // reverse — it belongs to staff accounts only.
        function applyRoleVisibility() {
            var isIntern = document.getElementById('type_intern').checked;
            var studentField = document.getElementById('studentIdField');
            var studentInput = document.getElementById('student_id');
            var usernameField = document.getElementById('usernameField');
            var usernameInput = document.getElementById('username');
            var approvalNote = document.getElementById('staffApprovalNote');
            if (studentField) { studentField.classList.toggle('hidden', !isIntern); }
            if (usernameField) { usernameField.classList.toggle('hidden', isIntern); }
            if (approvalNote) { approvalNote.classList.toggle('hidden', isIntern); }
            if (isIntern) {
                studentInput.setAttribute('required', 'required');
                if (usernameInput) {
                    usernameInput.removeAttribute('required');
                    usernameInput.setCustomValidity('');
                    usernameInput.value = '';
                    setFieldError('username', '');
                }
            } else {
                studentInput.removeAttribute('required');
                studentInput.setCustomValidity('');
                studentInput.value = '';
                setFieldError('student_id', '');
                if (usernameInput) { usernameInput.setAttribute('required', 'required'); }
            }
        }

        // Changing role starts the later parts fresh — clear their fields so
        // nothing carries over from the previously selected role.
        function clearDetailFields() {
            ['student_id', 'username', 'first_name', 'last_name', 'email', 'password', 'password_confirmation'].forEach(function (id) {
                var el = document.getElementById(id);
                if (!el) { return; }
                el.value = '';
                el.setCustomValidity('');
                setFieldError(id, '');
            });
            evaluatePassword();
        }

        Array.prototype.forEach.call(form.querySelectorAll('input[name="account_type"]'), function (radio) {
            radio.addEventListener('change', function () {
                applyRoleVisibility();
                clearDetailFields();
            });
        });

        // --- Live "already taken?" checks + Student ID format -------------
        // Debounced calls ask the server whether a Student ID / email /
        // username / full name is already registered, surfacing a clash
        // inline before submit. The server-side rules stay the source of
        // truth.
        var studentId = document.getElementById('student_id');
        var username = document.getElementById('username');
        var email = document.getElementById('email');
        var firstName = document.getElementById('first_name');
        var lastName = document.getElementById('last_name');
        var csrfToken = (form.querySelector('input[name="_token"]') || {}).value || '';

        function postAvailability(payload) {
            return fetch("{{ route('register.availability') }}", {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'Accept': 'application/json',
                    'X-CSRF-TOKEN': csrfToken
                },
                body: JSON.stringify(payload)
            }).then(function (response) {
                return response.ok ? response.json() : null;
            }).catch(function () {
                return null;
            });
        }

        function debounce(fn, wait) {
            var timer;
            return function () {
                var context = this, args = arguments;
                clearTimeout(timer);
                timer = setTimeout(function () { fn.apply(context, args); }, wait);
            };
        }

        // Each checker ignores stale replies (value changed since the request)
        // and only *sets* a taken-error — the input handlers clear it on edit.
        var checkStudentIdTaken = debounce(function () {
            var value = studentId.value.trim();
            if (value === '' || !/^\d+$/.test(value)) { return; }
            postAvailability({ field: 'student_id', value: value }).then(function (res) {
                if (!res || studentId.value.trim() !== value) { return; }
                if (res.taken) {
                    studentId.setCustomValidity('That Student ID is already registered.');
                    setFieldError('student_id', 'That Student ID is already registered.');
                }
            });
        }, 450);

        var checkUsernameTaken = debounce(function () {
            var value = username.value.trim();
            if (value === '') { return; }
            postAvailability({ field: 'username', value: value }).then(function (res) {
                if (!res || username.value.trim() !== value) { return; }
                if (res.taken) {
                    username.setCustomValidity('That username is already taken.');
                    setFieldError('username', 'That username is already taken.');
                }
            });
        }, 450);

        var checkEmailTaken = debounce(function () {
            var value = email.value.trim();
            if (!/^[^@\s]+@[^@\s]+\.[^@\s]+$/.test(value)) { return; }
            postAvailability({ field: 'email', value: value }).then(function (res) {
                if (!res || email.value.trim() !== value) { return; }
                if (res.taken) {
                    email.setCustomValidity('That email is already registered.');
                    setFieldError('email', 'That email is already registered — try signing in instead.');
                }
            });
        }, 450);

        var checkNameTaken = debounce(function () {
            var first = firstName.value.trim(), last = lastName.value.trim();
            if (first === '' || last === '') { return; }
            postAvailability({ field: 'full_name', first_name: first, last_name: last }).then(function (res) {
                if (!res || firstName.value.trim() !== first || lastName.value.trim() !== last) { return; }
                if (res.taken) {
                    lastName.setCustomValidity('An account with this name already exists.');
                    setFieldError('last_name', 'An account with this name already exists.');
                }
            });
        }, 450);

        // Student ID: digits only (live), then availability once it's clean.
        studentId.addEventListener('input', function () {
            var value = studentId.value;
            studentId.setCustomValidity('');
            setFieldError('student_id', '');
            if (value !== '' && !/^\d+$/.test(value)) {
                studentId.setCustomValidity('Digits only.');
                setFieldError('student_id', 'Student ID must contain digits only (0–9).');
                return;
            }
            checkStudentIdTaken();
        });

        // Email + username + full name: clear any prior "taken" flag as they
        // edit, then re-check once they pause typing. (Username is hidden and
        // emptied for interns, so its checker only ever fires for staff.)
        email.addEventListener('input', function () {
            email.setCustomValidity('');
            setFieldError('email', '');
            checkEmailTaken();
        });

        username.addEventListener('input', function () {
            username.setCustomValidity('');
            setFieldError('username', '');
            checkUsernameTaken();
        });

        function onNameInput() {
            lastName.setCustomValidity('');
            setFieldError('last_name', '');
            setFieldError('first_name', '');
            checkNameTaken();
        }
        firstName.addEventListener('input', onNameInput);
        lastName.addEventListener('input', onNameInput);

        // --- Password: live requirements + confirmation match -------------
        var password = document.getElementById('password');
        var passwordConfirm = document.getElementById('password_confirmation');
        var reqItems = Array.prototype.slice.call(document.querySelectorAll('#pwReqs li'));

        function evaluatePassword() {
            var value = password.value;
            var checks = {
                length: value.length >= 8,
                upper: /[A-Z]/.test(value),
                lower: /[a-z]/.test(value),
                number: /\d/.test(value),
                symbol: /[^A-Za-z0-9]/.test(value)
            };
            var allMet = true;
            reqItems.forEach(function (li) {
                var met = checks[li.getAttribute('data-rule')] === true;
                li.classList.toggle('ok', met);
                if (!met) { allMet = false; }
            });
            // The checklist is the live feedback; once the user is typing keep
            // the inline message clear and gate Next until every box is ticked.
            if (value !== '') { setFieldError('password', ''); }
            password.setCustomValidity(value !== '' && !allMet ? 'Password does not meet all the requirements.' : '');
            evaluateConfirm();
        }

        function evaluateConfirm() {
            if (passwordConfirm.value === '') {
                passwordConfirm.setCustomValidity('');
                setFieldError('password_confirmation', '');
            } else if (passwordConfirm.value !== password.value) {
                passwordConfirm.setCustomValidity('Passwords do not match.');
                setFieldError('password_confirmation', 'Passwords do not match.');
            } else {
                passwordConfirm.setCustomValidity('');
                setFieldError('password_confirmation', '');
            }
        }

        password.addEventListener('input', evaluatePassword);
        passwordConfirm.addEventListener('input', evaluateConfirm);

        // --- Suggest a strong password ------------------------------------
        // Generate a password that satisfies every rule, drop it into both
        // fields, reveal them so the user can read/copy it, then re-run the
        // live checklist so every box ticks and Next is unblocked.
        var suggestBtn = document.getElementById('suggestPassword');
        if (suggestBtn) {
            suggestBtn.addEventListener('click', function () {
                var suggestion = generateStrongPassword();
                password.value = suggestion;
                passwordConfirm.value = suggestion;
                revealPassword('password');
                revealPassword('password_confirmation');
                evaluatePassword();
                password.focus();
            });
        }

        // Force a password field into its revealed state, matching the eye
        // toggle (open eye hidden, slashed eye shown).
        function revealPassword(id) {
            var input = document.getElementById(id);
            if (!input) { return; }
            input.type = 'text';
            var toggle = form.querySelector('.password-toggle[data-target="' + id + '"]');
            if (toggle) {
                var open = toggle.querySelector('.eye-open');
                var closed = toggle.querySelector('.eye-closed');
                if (open) { open.classList.add('hidden'); }
                if (closed) { closed.classList.remove('hidden'); }
            }
        }

        // Build a 16-char password guaranteed to include an upper- and a
        // lower-case letter, a digit, and a symbol — so it clears every rule.
        // Ambiguous characters (O/0, l/1, etc.) are left out to keep it
        // readable if the user needs to type it somewhere else.
        function generateStrongPassword() {
            var upper = 'ABCDEFGHJKLMNPQRSTUVWXYZ';
            var lower = 'abcdefghijkmnpqrstuvwxyz';
            var digits = '23456789';
            var symbols = '!@#$%^&*?';
            var all = upper + lower + digits + symbols;

            function pick(set) {
                return set.charAt(randomInt(set.length));
            }

            var chars = [pick(upper), pick(lower), pick(digits), pick(symbols)];
            while (chars.length < 16) {
                chars.push(pick(all));
            }

            // Fisher–Yates shuffle so the four guaranteed characters aren't
            // always sitting in the first four positions.
            for (var i = chars.length - 1; i > 0; i--) {
                var j = randomInt(i + 1);
                var tmp = chars[i];
                chars[i] = chars[j];
                chars[j] = tmp;
            }

            return chars.join('');
        }

        // Uniform-ish random int in [0, max) — cryptographically strong where
        // the browser supports it, with a Math.random fallback.
        function randomInt(max) {
            if (window.crypto && window.crypto.getRandomValues) {
                var buf = new Uint32Array(1);
                window.crypto.getRandomValues(buf);
                return buf[0] % max;
            }
            return Math.floor(Math.random() * max);
        }

        // Initial paint — reflect the starting role and reset the checklist.
        applyRoleVisibility();
        evaluatePassword();

        // --- Multi-step wizard --------------------------------------------
        var steps = Array.prototype.slice.call(form.querySelectorAll('.wizard-step'));
        var items = Array.prototype.slice.call(document.querySelectorAll('.wizard-progress-item'));
        var backBtn = form.querySelector('[data-wizard="back"]');
        var nextBtn = form.querySelector('[data-wizard="next"]');
        var submitBtn = form.querySelector('[data-wizard="submit"]');
        var total = steps.length;
        var current = parseInt(form.getAttribute('data-initial-step'), 10) || 1;

        form.classList.add('is-enhanced');

        function render() {
            steps.forEach(function (s) {
                s.classList.toggle('is-current', parseInt(s.getAttribute('data-step'), 10) === current);
            });
            items.forEach(function (it) {
                var n = parseInt(it.getAttribute('data-step'), 10);
                it.classList.toggle('is-active', n === current);
                it.classList.toggle('is-done', n < current);
            });
            backBtn.style.display = current > 1 ? '' : 'none';
            nextBtn.style.display = current < total ? '' : 'none';
            submitBtn.style.display = current === total ? '' : 'none';
        }

        // Run the browser's native validation for just this step's fields
        // before letting the user advance.
        function validateStep(index) {
            var fields = steps[index - 1].querySelectorAll('input, select, textarea');
            for (var i = 0; i < fields.length; i++) {
                if (!fields[i].checkValidity()) {
                    fields[i].reportValidity();
                    return false;
                }
            }
            return true;
        }

        function goNext() {
            if (validateStep(current) && current < total) {
                current++;
                render();
            }
        }

        nextBtn.addEventListener('click', goNext);
        backBtn.addEventListener('click', function () {
            if (current > 1) { current--; render(); }
        });

        // Pressing Enter on a non-final step advances instead of submitting
        // the form early.
        form.addEventListener('submit', function (e) {
            if (current < total) {
                e.preventDefault();
                goNext();
            }
        });

        render();
    });
</script>
@endpush
