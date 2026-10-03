<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>@yield('title', 'Sign in') — NORSU OJT Tracker</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    <style>
        /* Guest auth screens are self-contained (no app sidebar). The
           distinctive split-panel look lives here in scoped .auth-* classes
           rather than utility classes, so the design renders even before a
           fresh `npm run build` picks up any new Tailwind utilities. Palette
           mirrors resources/css/app.css: indigo brand + neutral grays. */
        :root {
            --brand-500: #6366f1;
            --brand-600: #4f46e5;
            --brand-700: #4338ca;
            --brand-900: #312e81;
            --border-soft: #e5e7eb;
            --text-muted: #6b7280;
            --ink: #111827;
        }
        * { box-sizing: border-box; }
        html, body { height: 100%; margin: 0; }
        body {
            font-family: 'Inter', ui-sans-serif, system-ui, -apple-system, 'Segoe UI', sans-serif;
            color: var(--ink);
            -webkit-font-smoothing: antialiased;
        }

        body.auth-body {
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 2rem 1rem;
            position: relative;
            overflow-x: hidden;
            background: #f9fafb;
        }
        /* Quiet dot-grid backdrop, matching the current login screen. */
        body.auth-body::before {
            content: "";
            position: absolute;
            inset: 0;
            background-image: radial-gradient(circle, rgba(17,24,39,0.05) 1.1px, transparent 1.1px);
            background-size: 22px 22px;
            pointer-events: none;
        }
        /* Soft indigo glow in the corner for depth. */
        body.auth-body::after {
            content: "";
            position: absolute;
            top: -120px;
            right: -120px;
            width: 420px;
            height: 420px;
            border-radius: 50%;
            background: radial-gradient(circle, rgba(79,70,229,0.12) 0%, rgba(79,70,229,0) 70%);
            pointer-events: none;
        }

        .auth-card-wrap {
            position: relative;
            z-index: 2;
            width: 100%;
            max-width: 460px;
        }
        .auth-body.has-split .auth-card-wrap { max-width: 960px; }

        /* Single-card (non-split) shell — reserved for simple guest pages. */
        .auth-card {
            background: #fff;
            border-radius: 18px;
            border: 1px solid var(--border-soft);
            box-shadow: 0 1px 2px rgba(17,24,39,0.04), 0 12px 32px rgba(17,24,39,0.08);
        }

        /* ---- Split auth card: sliding welcome panel + form ----
           Login: panel LEFT / form right. Register: panel RIGHT / form left.
           The .pre class parks the panel on the opposite side; a tiny script
           removes it right after load so it slides into place, giving the
           "panel transfers across" feel when moving between the two pages. */
        .auth-split-card {
            position: relative;
            background: #fff;
            border-radius: 18px;
            border: 1px solid var(--border-soft);
            box-shadow: 0 1px 2px rgba(17,24,39,0.04), 0 18px 40px rgba(17,24,39,0.10);
            overflow: hidden;
            display: grid;
            grid-template-columns: 1fr 1fr;
            min-height: 660px;
        }
        .auth-form-col {
            grid-column: 1;
            padding: 3rem 2.75rem;
            display: flex;
            flex-direction: column;
            justify-content: center;
            overflow-y: auto;
            transition: opacity .5s ease .15s, transform .5s ease .15s;
        }
        .auth-form-col.col-right { grid-column: 2; }
        .auth-split-card.pre .auth-form-col { opacity: 0; transform: translateY(12px); }

        .auth-welcome-panel {
            position: absolute;
            top: 0;
            bottom: 0;
            left: 50%;
            width: 50%;
            z-index: 3;
            display: flex;
            align-items: center;
            justify-content: center;
            text-align: center;
            padding: 3rem 2.25rem;
            color: #fff;
            background: linear-gradient(135deg, var(--brand-900) 0%, var(--brand-700) 50%, var(--brand-600) 100%);
            transition: left .65s cubic-bezier(.65, 0, .35, 1);
        }
        .auth-split-card.panel-left .auth-welcome-panel { left: 0; }
        .auth-split-card.pre.panel-left .auth-welcome-panel { left: 50%; }
        .auth-split-card.pre.panel-right .auth-welcome-panel { left: 0; }
        .auth-welcome-panel::before,
        .auth-welcome-panel::after {
            content: "";
            position: absolute;
            border-radius: 50%;
            pointer-events: none;
        }
        .auth-welcome-panel::before { width: 300px; height: 300px; top: -90px; left: -90px; background: rgba(255,255,255,0.08); }
        .auth-welcome-panel::after { width: 240px; height: 240px; bottom: -80px; right: -70px; background: rgba(255,255,255,0.06); }
        .welcome-inner { position: relative; z-index: 1; max-width: 340px; }
        .welcome-inner h2 { color: #fff; font-weight: 800; font-size: clamp(1.6rem, 2.4vw, 2rem); letter-spacing: -0.02em; margin: 0 0 .75rem; }
        .welcome-inner p { color: rgba(255,255,255,0.85); font-size: .95rem; line-height: 1.6; margin: 0; }
        .welcome-cta-hint {
            font-size: .72rem;
            text-transform: uppercase;
            letter-spacing: .1em;
            color: rgba(255,255,255,0.7);
            margin: 1.75rem 0 0.6rem;
        }
        .btn-welcome-cta {
            display: inline-flex;
            align-items: center;
            gap: .5rem;
            background: #fff;
            color: var(--brand-700);
            border-radius: 999px;
            padding: .6rem 1.9rem;
            font-weight: 600;
            font-size: .9rem;
            text-decoration: none;
            box-shadow: 0 8px 20px rgba(17,24,39,0.18);
            transition: transform .15s ease, box-shadow .15s ease;
        }
        .btn-welcome-cta:hover { transform: translateY(-1px); box-shadow: 0 10px 24px rgba(17,24,39,0.24); }
        .welcome-brand-icon {
            width: 52px; height: 52px;
            border-radius: 14px;
            background: rgba(255,255,255,0.16);
            display: flex; align-items: center; justify-content: center;
            margin: 0 auto 1.4rem;
            color: #fff; font-weight: 700; font-size: 1.35rem;
        }

        /* ---- Form-side building blocks (scoped, palette-matched) ---- */
        .form-brand { display: none; }               /* only shows when panel is hidden (mobile) */
        .form-brand-icon {
            width: 44px; height: 44px; border-radius: 12px;
            background: var(--ink); color: #fff;
            display: flex; align-items: center; justify-content: center;
            font-weight: 700; margin: 0 auto .75rem;
        }
        .auth-heading { font-size: 1.35rem; font-weight: 700; letter-spacing: -0.02em; color: var(--ink); margin: 0 0 .25rem; }
        .auth-subheading { font-size: .9rem; color: var(--text-muted); margin: 0 0 1.5rem; }

        .field { margin-bottom: 1.1rem; }
        .field-label { display: block; font-size: .82rem; font-weight: 500; color: #374151; margin-bottom: .4rem; }
        .auth-input {
            width: 100%;
            padding: .65rem .85rem;
            border-radius: 10px;
            border: 1px solid var(--border-soft);
            background: #fff;
            font-size: .9rem;
            color: var(--ink);
            transition: border-color .15s ease, box-shadow .15s ease;
        }
        .auth-input::placeholder { color: #9ca3af; }
        .auth-input:focus {
            outline: none;
            border-color: var(--brand-500);
            box-shadow: 0 0 0 3px rgba(99,102,241,0.15);
        }

        /* Password field with a show/hide eye toggle. */
        .password-group { position: relative; display: flex; align-items: stretch; }
        .password-group .auth-input { padding-right: 2.75rem; }
        .password-toggle {
            position: absolute;
            right: .3rem; top: 50%;
            transform: translateY(-50%);
            border: none; background: transparent;
            color: #9ca3af; cursor: pointer;
            padding: .4rem .5rem; line-height: 0;
            border-radius: 8px;
        }
        .password-toggle:hover { color: var(--ink); }

        /* Role toggle — segmented radio buttons ("I am a…"). */
        .role-toggle { display: grid; grid-template-columns: repeat(3, 1fr); gap: .5rem; }
        .role-toggle input { position: absolute; opacity: 0; pointer-events: none; }
        .role-toggle label {
            display: flex; align-items: center; justify-content: center;
            text-align: center;
            padding: .6rem .35rem;
            border: 1px solid var(--border-soft);
            border-radius: 10px;
            font-size: .82rem; font-weight: 500;
            color: var(--text-muted);
            cursor: pointer;
            transition: all .15s ease;
            user-select: none;
        }
        .role-toggle label:hover { border-color: var(--brand-300, #a5b4fc); color: var(--ink); }
        .role-toggle input:checked + label {
            background: var(--brand-600);
            border-color: var(--brand-600);
            color: #fff;
            box-shadow: 0 4px 12px rgba(79,70,229,0.25);
        }
        .role-toggle input:focus-visible + label { box-shadow: 0 0 0 3px rgba(99,102,241,0.3); }

        .checkbox-row { display: flex; align-items: flex-start; gap: .55rem; font-size: .82rem; color: var(--text-muted); margin-bottom: 1.2rem; }
        .checkbox-row input { margin-top: .15rem; width: 15px; height: 15px; accent-color: var(--brand-600); }
        .checkbox-row a { color: var(--brand-600); font-weight: 500; text-decoration: none; }
        .checkbox-row a:hover { text-decoration: underline; }

        .btn-primary {
            width: 100%;
            border: none;
            background: var(--brand-600);
            color: #fff;
            font-size: .92rem; font-weight: 600;
            padding: .7rem 1rem;
            border-radius: 10px;
            cursor: pointer;
            transition: background .15s ease;
        }
        .btn-primary:hover { background: var(--brand-700); }

        .auth-alt { text-align: center; font-size: .85rem; color: var(--text-muted); margin-top: 1.25rem; }
        .auth-alt a { color: var(--brand-600); font-weight: 500; text-decoration: none; }
        .auth-alt a:hover { text-decoration: underline; }
        .field-error { display: block; margin-top: .35rem; font-size: .78rem; color: #dc2626; }

        .hidden { display: none !important; }

        /* Mobile: hide the welcome panel, collapse to a single column, and
           reveal the compact brand block above the form. */
        @media (max-width: 860px) {
            .auth-welcome-panel { display: none; }
            .auth-split-card { grid-template-columns: 1fr; min-height: 0; }
            .auth-form-col, .auth-form-col.col-right { grid-column: 1; padding: 2.25rem 1.75rem; }
            .form-brand { display: block; text-align: center; margin-bottom: 1.5rem; }
        }
        @media (prefers-reduced-motion: reduce) {
            .auth-welcome-panel, .auth-form-col { transition: none; }
        }
    </style>
    @stack('styles')
</head>
@php
    // Views opt into the two-panel split layout by defining a 'welcome_panel'
    // section; 'welcome_side' picks which side the panel lands on (left|right).
    $hasWelcomePanel = trim((string) $__env->yieldContent('welcome_panel')) !== '';
    $panelSide = trim((string) $__env->yieldContent('welcome_side')) ?: 'left';
@endphp
<body class="auth-body {{ $hasWelcomePanel ? 'has-split' : '' }}">

    {{-- Success flashes + validation summaries float in as auto-dismissing
         toasts instead of banners that shove the card around. Field-level
         @error messages remain next to their inputs on the forms themselves. --}}
    @include('partials.toast')

    <div class="auth-card-wrap">
        @if($hasWelcomePanel)
            <div class="auth-split-card panel-{{ $panelSide }} pre" id="authSplitCard">
                <div class="auth-form-col {{ $panelSide === 'left' ? 'col-right' : 'col-left' }}">
                    @yield('content')
                </div>
                <div class="auth-welcome-panel">
                    @yield('welcome_panel')
                </div>
            </div>
        @else
            @yield('content')
        @endif
    </div>

    <script>
        // Kick off the panel slide-in one frame after load so the transition
        // plays from the parked (.pre) position instead of snapping into place.
        document.addEventListener('DOMContentLoaded', function () {
            var card = document.getElementById('authSplitCard');
            if (card) {
                requestAnimationFrame(function () {
                    requestAnimationFrame(function () {
                        card.classList.remove('pre');
                    });
                });
            }
        });

        // Show/hide password toggle — flips the input type and the eye icon.
        function togglePassword(button) {
            var input = document.getElementById(button.getAttribute('data-target'));
            if (!input) { return; }
            var showing = input.type === 'text';
            input.type = showing ? 'password' : 'text';
            button.querySelector('.eye-open').classList.toggle('hidden', !showing);
            button.querySelector('.eye-closed').classList.toggle('hidden', showing);
        }
    </script>
    @stack('scripts')
    <a href="{{ route('privacy') }}"
       class="fixed bottom-3 inset-x-0 text-center text-[11px] text-gray-400 hover:text-gray-500">Privacy Policy</a>
</body>
</html>
