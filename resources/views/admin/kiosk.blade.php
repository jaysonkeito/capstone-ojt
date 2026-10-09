<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>Time In / Out Station — NORSU OJT</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    {{-- jsQR decodes QR codes from the webcam frames for Camera mode. Loaded
         from CDN like the fonts above; if it (or the network) is unavailable,
         Camera mode degrades gracefully and Scanner still works. --}}
    <script src="https://cdn.jsdelivr.net/npm/jsqr@1.4.0/dist/jsQR.js"></script>
    @vite('resources/css/app.css')
    <style>
        body { font-family: 'Inter', ui-sans-serif, system-ui, sans-serif; }
        /* Keep the capture field out of sight but still focusable so the
           USB scanner's "typed" characters always land somewhere. */
        #scanInput { position: fixed; top: 0; left: 0; width: 1px; height: 1px; opacity: 0; border: 0; padding: 0; }
        .fade-in { animation: fadeIn .18s ease-out; }
        @keyframes fadeIn { from { opacity: 0; transform: translateY(6px); } to { opacity: 1; transform: none; } }
        .mode-btn { color: rgb(156 163 175); padding: .4rem .95rem; border-radius: .7rem; font-size: .8125rem; font-weight: 500; transition: color .15s, background-color .15s; }
        .mode-btn:hover { color: #fff; }
        .mode-btn.active { background: rgba(255,255,255,.12); color: #fff; }
        /* A locked tab greys out, shows the lock glyph after its label, and
           can't be opened — the desk operator sees it exists but isn't
           available. */
        .mode-btn.mode-locked { color: rgb(87 83 94); cursor: not-allowed; }
        .mode-btn.mode-locked::after { content: ' 🔒'; font-size: .7em; }
    </style>
</head>
<body class="bg-gray-900 text-white h-screen overflow-hidden">

    {{-- Capture field — refocused only in Scanner mode so a scan is never missed. --}}
    <input id="scanInput" type="text" autocomplete="off" autocorrect="off" autocapitalize="off" spellcheck="false" aria-hidden="true" tabindex="-1">

    <div class="h-screen flex flex-col">

        {{-- Top bar --}}
        <div class="flex items-center justify-between px-6 py-4 border-b border-white/10">
            <div class="flex items-center gap-2.5">
                <div class="w-8 h-8 rounded-lg bg-white/10 flex items-center justify-center text-sm font-bold">N</div>
                <div class="leading-tight">
                    <p class="font-semibold text-sm tracking-tight">NORSU CAS — OJT</p>
                    <p class="text-[10px] text-gray-400 uppercase tracking-widest">Time In / Out Station</p>
                </div>
            </div>
            <div class="flex items-center gap-6">
                <div class="text-right leading-tight">
                    <p id="clock" class="text-lg font-semibold tabular-nums">—</p>
                    <p id="date" class="text-[11px] text-gray-400">—</p>
                </div>
            @if(auth()->user()->isOffice())
                {{-- Office scanner accounts have no dashboard behind the
                     station — Exit would 403 them. Logout returns to the
                     sign-in page instead. --}}
                <form method="POST" action="{{ route('logout') }}">
                    @csrf
                    <button type="submit" class="text-xs font-medium text-gray-400 hover:text-white px-3 py-1.5 rounded-lg hover:bg-white/10 transition">Logout</button>
                </form>
            @else
                <a href="{{ route('admin.dashboard') }}"
                   class="text-xs font-medium text-gray-400 hover:text-white px-3 py-1.5 rounded-lg hover:bg-white/10 transition">Exit</a>
            @endif
            </div>
        </div>

        {{-- Split station: left 60% the scan flow, right 40% the day's
             logbook (read-only) over the live capture camera. --}}
        <div class="flex-1 grid grid-cols-[6fr_4fr] min-h-0">
            <div class="flex flex-col border-r border-white/10 min-w-0">

        {{-- Mode toggle — Scanner (USB QR box), Camera (webcam), or Student ID (manual entry).
             Which tabs are available is locked from the admin Settings page: a locked
             tab greys out with a lock badge and cannot be opened on this station. --}}
        <div class="flex justify-center pt-5 pb-1">
            <div class="inline-flex items-center gap-1 rounded-2xl bg-white/5 border border-white/10 p-1">
                <button type="button" data-mode="scanner" class="mode-btn active">Scanner</button>
                <button type="button" data-mode="camera" class="mode-btn">Camera</button>
                <button type="button" data-mode="manual" class="mode-btn">Student ID</button>
            </div>
        </div>

        {{-- Stage --}}
        <div class="flex-1 flex items-center justify-center px-6 py-8">
            <div class="w-full max-w-md">

                {{-- Scanner idle: waiting for a USB-scanner read --}}
                <div id="idle" class="text-center">
                    <div class="w-24 h-24 rounded-3xl bg-white/5 border border-white/10 flex items-center justify-center mx-auto mb-7">
                        <svg width="46" height="46" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round" class="text-gray-300">
                            <path d="M4 7V5a1 1 0 0 1 1-1h2"/><path d="M4 17v2a1 1 0 0 0 1 1h2"/>
                            <path d="M20 7V5a1 1 0 0 0-1-1h-2"/><path d="M20 17v2a1 1 0 0 1-1 1h-2"/>
                            <path d="M4 12h16" class="text-brand-400" stroke="currentColor"/>
                        </svg>
                    </div>
                    <h1 class="text-2xl font-bold tracking-tight">Ready to scan</h1>
                    <p class="text-gray-400 mt-2 leading-relaxed">Show your personal OJT QR to the scanner to time in or out.<br>Open it from <span class="text-gray-200 font-medium">My QR Code</span> on your phone, or use your printed card.</p>
                    {{-- Scanner status light --}}
                    <p class="mt-8 inline-flex items-center gap-2 text-xs text-gray-500">
                        <span class="relative flex h-2 w-2">
                            <span id="scanPing" class="animate-ping absolute inline-flex h-full w-full rounded-full bg-emerald-400 opacity-75"></span>
                            <span id="scanDot" class="relative inline-flex rounded-full h-2 w-2 bg-emerald-500"></span>
                        </span>
                        <span id="scanStatusText">Listening for scans</span>
                    </p>

                </div>

                {{-- Camera: live webcam preview, decoded client-side by jsQR --}}
                <div id="cameraPanel" class="hidden text-center">
                    <div class="relative rounded-3xl overflow-hidden bg-black border border-white/10 aspect-square max-w-xs mx-auto">
                        {{-- Flip the preview so it matches the real world — this webcam's
                             feed comes through mirrored. Decoding is unaffected: jsQR reads
                             the raw frame from the canvas, not the flipped preview. --}}
                        <video id="video" class="w-full h-full object-cover -scale-x-100" autoplay muted playsinline></video>
                        <div class="absolute inset-0 pointer-events-none">
                            <div class="absolute inset-7 border-2 border-white/40 rounded-2xl"></div>
                        </div>
                    </div>
                    <p id="cameraStatus" class="text-gray-400 mt-5 leading-relaxed">Hold the intern's QR up to the camera to record their time.</p>
                </div>

                {{-- Student ID: manual entry tab --}}
                <div id="manualPanel" class="hidden text-center">
                    <div class="w-24 h-24 rounded-3xl bg-white/5 border border-white/10 flex items-center justify-center mx-auto mb-7">
                        <svg width="46" height="46" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round" class="text-gray-300">
                            <rect x="2" y="4" width="20" height="16" rx="2"/><path d="M6 8h.01"/><path d="M10 8h.01"/><path d="M14 8h.01"/><path d="M18 8h.01"/>
                            <path d="M6 12h.01"/><path d="M10 12h.01"/><path d="M14 12h.01"/><path d="M18 12h.01"/><path d="M7 16h10"/>
                        </svg>
                    </div>
                    <h1 class="text-2xl font-bold tracking-tight">Enter Student ID</h1>
                    <p class="text-gray-400 mt-2 leading-relaxed">Type the Student ID below to record the intern's time.</p>
                    <form id="manualForm" class="mt-8 max-w-xs mx-auto space-y-4" autocomplete="off">
                        <input id="studentIdInput" type="text" autocomplete="off" autocorrect="off" autocapitalize="off" spellcheck="false"
                            placeholder="Student ID"
                            class="w-full text-base tracking-wide px-4 py-3 rounded-xl bg-white/5 border border-white/15 text-white placeholder:text-gray-500 focus:border-brand-400 focus:ring-2 focus:ring-brand-500/25 focus:outline-none transition">
                        <button type="submit" class="w-full bg-brand-600 hover:bg-brand-700 text-white text-sm font-medium py-3 rounded-xl transition">Record time</button>
                    </form>
                </div>

                {{-- Processing --}}
                <div id="processing" class="hidden text-center">
                    <div class="w-16 h-16 rounded-full border-4 border-white/10 border-t-white/70 animate-spin mx-auto mb-6"></div>
                    <p class="text-gray-300">Reading…</p>
                </div>

                {{-- Result (built by JS) --}}
                <div id="result" class="hidden"></div>

            </div>
        </div>

        <div class="px-6 py-3 text-center text-[11px] text-gray-600 border-t border-white/10">
            Leave this page open on the front-desk computer. Interns can scan by device, camera, or Student ID.
        </div>
            </div>

            {{-- Right half: duty logbook (read-only) over the capture scanner --}}
            <div class="grid grid-rows-2 gap-4 p-4 min-h-0">

                {{-- Duty logbook — today's entries as they happen, read-only
                     like the exported form: one header, four slot columns. --}}
                <div class="rounded-3xl border border-white/10 bg-white/5 flex flex-col overflow-hidden min-h-0">
                    <div class="px-4 py-3 border-b border-white/10 flex items-center justify-between">
                        <h2 class="text-[11px] font-medium uppercase tracking-widest text-gray-400">Duty logbook — today</h2>
                        <span id="stationLogCount" class="text-xs text-gray-500 tabular-nums">{{ $stationLogs->count() }}</span>
                    </div>
                    <div class="flex-1 overflow-y-auto min-h-0">
                        <table class="w-full text-[12px]">
                            <thead class="sticky top-0 bg-gray-900 z-10">
                                <tr class="text-left text-[9px] uppercase tracking-widest text-gray-400 border-b border-white/10">
                                    <th class="px-3 py-2 font-medium">Name</th>
                                    <th class="px-2 py-2 font-medium">AM In</th>
                                    <th class="px-2 py-2 font-medium">AM Out</th>
                                    <th class="px-2 py-2 font-medium">PM In</th>
                                    <th class="px-2 py-2 font-medium">PM Out</th>
                                </tr>
                            </thead>
                            <tbody id="stationLog" class="divide-y divide-white/5">
                                @forelse($stationLogs as $log)
                                    @php
                                        $punch = $log->latest_punch;
                                        $shortName = $log->user->last_name.', '.mb_substr($log->user->first_name, 0, 1).'.';
                                        $fmt = fn (?string $v) => $v ? \Illuminate\Support\Carbon::parse($v)->format('h:i A') : null;
                                        // One slot column: the spine time, and — when the intern
                                        // stepped out and came back — the "(2)" time beneath it in
                                        // smaller text. The newest punch (spine or (2)) highlights.
                                        $slotPair = function (?string $main, ?string $second, ?string $latestSlot, string $mainSlot, string $secondSlot) use ($fmt) {
                                            $mainText = $fmt($main) ?? '<span class="text-gray-600">—</span>';
                                            $mainClass = $latestSlot === $mainSlot ? 'text-brand-300 font-medium' : ($fmt($main) ? 'text-gray-300' : '');
                                            $html = '<span class="'.$mainClass.'">'.$mainText.'</span>';
                                            if ($second !== null) {
                                                $secondClass = $latestSlot === $secondSlot ? 'text-brand-300 font-medium' : 'text-gray-500';
                                                $html .= '<div class="text-[10px] leading-tight '.$secondClass.'">(2) '.$fmt($second).'</div>';
                                            }
                                            return $html;
                                        };
                                    @endphp
                                    <tr data-user="{{ $log->user_id }}">
                                        <td class="px-3 py-2 text-white truncate max-w-[9rem]" title="{{ $log->user->full_name }}">{{ $shortName }}</td>
                                        <td class="px-2 py-2 tabular-nums whitespace-nowrap align-top">{!! $slotPair($log->am_time_in, $log->am_time_in_2, $punch['slot'] ?? null, 'am_time_in', 'am_time_in_2') !!}</td>
                                        <td class="px-2 py-2 tabular-nums whitespace-nowrap align-top">{!! $slotPair($log->am_time_out, $log->am_time_out_2, $punch['slot'] ?? null, 'am_time_out', 'am_time_out_2') !!}</td>
                                        <td class="px-2 py-2 tabular-nums whitespace-nowrap align-top">{!! $slotPair($log->pm_time_in, $log->pm_time_in_2, $punch['slot'] ?? null, 'pm_time_in', 'pm_time_in_2') !!}</td>
                                        <td class="px-2 py-2 tabular-nums whitespace-nowrap align-top">{!! $slotPair($log->pm_time_out, $log->pm_time_out_2, $punch['slot'] ?? null, 'pm_time_out', 'pm_time_out_2') !!}</td>
                                    </tr>
                                @empty
                                    <tr><td colspan="5" class="px-5 py-10 text-center text-gray-500 text-sm">No scans yet today.</td></tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>

                {{-- Capture scanner — the live webcam whose frame is saved
                     with each scan, so the intern sees themselves being
                     recorded. --}}
                <div id="guardCam" class="rounded-3xl overflow-hidden border border-white/10 bg-black relative min-h-0">
                    <video id="guardVideo" class="absolute inset-0 w-full h-full object-cover -scale-x-100" autoplay muted playsinline></video>
                    <div class="absolute top-3 left-3 flex items-center gap-1.5 rounded-md bg-black/60 px-2 py-1">
                        <span id="guardDot" class="w-1.5 h-1.5 rounded-full bg-emerald-400"></span>
                        <span id="guardNote" class="text-[10px] font-medium tracking-wide text-gray-200 uppercase">Capturing</span>
                    </div>
                    <div class="absolute bottom-0 inset-x-0 px-4 py-2.5 bg-gradient-to-t from-black/80 to-transparent">
                        <p class="text-[11px] text-gray-300">Capture Scanner — a snapshot is saved with every scan.</p>
                    </div>
                </div>
            </div>
        </div>
    </div>

    {{-- Capture field moved above; the verification camera lives in the
         right-bottom panel and is driven by the same JS as before. --}}

    <script>
        (function () {
            let mode = 'scanner'; // 'scanner' | 'camera' | 'manual'

            const input = document.getElementById('scanInput');
            const idle = document.getElementById('idle');
            const cameraPanel = document.getElementById('cameraPanel');
            const manualPanel = document.getElementById('manualPanel');
            const processing = document.getElementById('processing');
            const result = document.getElementById('result');
            const video = document.getElementById('video');
            const cameraStatus = document.getElementById('cameraStatus');
            const manualForm = document.getElementById('manualForm');
            const studentIdInput = document.getElementById('studentIdInput');
            const scanPing = document.getElementById('scanPing');
            const scanDot = document.getElementById('scanDot');
            const scanStatusText = document.getElementById('scanStatusText');

            const scanUrl = "{{ route('admin.kiosk.scan') }}";
            const manualUrl = "{{ route('admin.kiosk.manual') }}";
            const pingUrl = "{{ route('admin.kiosk.ping') }}";
            const csrf = document.querySelector('meta[name=csrf-token]').content;

            let resetTimer = null;
            let busy = false; // a POST is in flight — don't overlap requests

            // ---- keep the capture field focused, but ONLY in Scanner mode ----
            function focusInput() {
                if (mode !== 'scanner') { return; }
                try { input.focus(); } catch (e) {}
            }

            // ---- Scanner status light ----
            function setScanStatus(kind) {
                const green = kind === 'listening';

                let dotColor = 'bg-emerald-500';
                if (kind === 'paused') { dotColor = 'bg-amber-500'; }
                scanDot.className = 'relative inline-flex rounded-full h-2 w-2 ' + dotColor;

                scanPing.classList.toggle('hidden', !green);

                scanStatusText.textContent = {
                    listening: 'Listening for scans',
                    paused: 'Paused — click or tap to resume',
                }[kind] || 'Listening for scans';

                scanStatusText.classList.toggle('text-amber-300', kind === 'paused');
            }

            function refreshScanStatus() {
                if (mode !== 'scanner') { return; }
                setScanStatus(document.hasFocus() && document.activeElement === input ? 'listening' : 'paused');
            }

            focusInput();
            refreshScanStatus();
            document.addEventListener('click', focusInput);
            document.addEventListener('touchend', focusInput);
            input.addEventListener('focus', refreshScanStatus);
            input.addEventListener('blur', () => setTimeout(() => { focusInput(); refreshScanStatus(); }, 40));
            window.addEventListener('focus', () => { focusInput(); refreshScanStatus(); });
            window.addEventListener('blur', refreshScanStatus);

            // ---- live clock ----
            function tick() {
                const now = new Date();
                document.getElementById('clock').textContent =
                    now.toLocaleTimeString([], { hour: 'numeric', minute: '2-digit', second: '2-digit' });
                document.getElementById('date').textContent =
                    now.toLocaleDateString([], { weekday: 'short', month: 'short', day: 'numeric' });
            }
            tick();
            setInterval(tick, 1000);

            // ---- keep-alive ----
            async function heartbeat() {
                try {
                    const res = await fetch(pingUrl, {
                        headers: { 'X-Requested-With': 'XMLHttpRequest' },
                        credentials: 'same-origin',
                        cache: 'no-store',
                    });
                    if (res.redirected || res.status === 401 || res.status === 419) {
                        window.location.reload();
                    }
                } catch (e) { /* offline for now — try again on the next tick */ }
            }
            setInterval(heartbeat, 10 * 60 * 1000);

            // ---- Scanner mode: USB scanner types characters then Enter ----
            input.addEventListener('keydown', (e) => {
                if (e.key === 'Enter') {
                    e.preventDefault();
                    const code = input.value.trim();
                    input.value = '';
                    if (code !== '') { submit(code); }
                }
            });

            // ---- Student ID tab: type a Student ID and record ----
            manualForm.addEventListener('submit', (e) => {
                e.preventDefault();
                const studentId = studentIdInput.value.trim();
                studentIdInput.value = '';
                if (studentId !== '') { submitManual(studentId); }
            });

            // ---- mode switching (locked tabs render greyed with a lock
            //      badge and can't be opened on this station) ----
            const modeButtons = document.querySelectorAll('.mode-btn');
            const lockedModes = @json($locks);

            function applyLocks() {
                modeButtons.forEach((btn) => {
                    const locked = lockedModes['lock_' + btn.dataset.mode] === true;
                    btn.classList.toggle('mode-locked', locked);
                    btn.disabled = locked;
                    btn.title = locked ? 'Locked by your supervisor' : '';
                });

                // If the current mode just got locked, fall back to the
                // first unlocked one.
                if (lockedModes['lock_' + mode] === true) {
                    const fallback = ['scanner', 'camera', 'manual'].find((m) => lockedModes['lock_' + m] !== true);
                    if (fallback) { switchMode(fallback); }
                }
            }

            modeButtons.forEach((btn) => {
                btn.addEventListener('click', () => {
                    if (btn.disabled) { return; }
                    switchMode(btn.dataset.mode);
                });
            });

            function updateModeButtons() {
                modeButtons.forEach((btn) => {
                    btn.classList.toggle('active', btn.dataset.mode === mode);
                });
            }

            function switchMode(next) {
                if (next === mode || busy) { return; }
                if (lockedModes['lock_' + next] === true) { return; }
                if (mode === 'camera') { stopCamera(); }

                mode = next;
                updateModeButtons();
                clearTimeout(resetTimer);

                if (mode === 'scanner') {
                    show(idle);
                    focusInput();
                    refreshScanStatus();
                } else if (mode === 'camera') {
                    show(cameraPanel);
                    startCamera();
                } else if (mode === 'manual') {
                    show(manualPanel);
                    setTimeout(() => { try { studentIdInput.focus(); } catch (e) {} }, 60);
                }
            }

            applyLocks();

            // ---- Camera mode: decode QR frames with jsQR ----
            let stream = null;
            let scanning = false;
            let rafId = null;
            let armed = true;
            let lastCode = null;
            let lastCodeAt = 0;
            const canvas = document.createElement('canvas');
            const ctx = canvas.getContext('2d', { willReadFrequently: true });

            const cameraLive = () => mode === 'camera' && !cameraPanel.classList.contains('hidden');

            function cameraError(msg) {
                cameraStatus.innerHTML =
                    '<span class="text-amber-300">' + esc(msg) + '</span><br>' +
                    '<span class="text-gray-500 text-sm">Use Scanner or the Student ID tab instead.</span>';
            }

            async function startCamera() {
                if (typeof jsQR === 'undefined') {
                    cameraError('QR reader could not load (no internet?).');
                    return;
                }
                if (!navigator.mediaDevices || !navigator.mediaDevices.getUserMedia) {
                    cameraError('No camera access on this computer.');
                    return;
                }
                cameraStatus.textContent = 'Hold the intern\u2019s QR up to the camera to record their time.';
                try {
                    stream = await navigator.mediaDevices.getUserMedia({
                        video: { facingMode: 'environment' },
                        audio: false,
                    });
                    video.srcObject = stream;
                    await video.play();
                    scanning = true;
                    armed = true;
                    lastCode = null;
                    rafId = requestAnimationFrame(scanFrame);
                } catch (e) {
                    cameraError('Could not open the camera. Allow camera access, and use a secure (https) connection.');
                }
            }

            function stopCamera() {
                scanning = false;
                if (rafId) { cancelAnimationFrame(rafId); rafId = null; }
                if (stream) {
                    stream.getTracks().forEach((t) => t.stop());
                    stream = null;
                }
                video.srcObject = null;
            }

            function scanFrame() {
                if (!scanning) { return; }
                try {
                    if (video.readyState === video.HAVE_ENOUGH_DATA && video.videoWidth > 0) {
                        canvas.width = video.videoWidth;
                        canvas.height = video.videoHeight;
                        ctx.drawImage(video, 0, 0, canvas.width, canvas.height);
                        const image = ctx.getImageData(0, 0, canvas.width, canvas.height);
                        const found = jsQR(image.data, image.width, image.height, { inversionAttempts: 'dontInvert' });

                        if (found && found.data && found.data.trim() !== '') {
                            const value = found.data.trim();
                            const now = Date.now();
                            const isRepeat = value === lastCode && (now - lastCodeAt) < 6000;
                            if (armed && !busy && !isRepeat && cameraLive()) {
                                armed = false;
                                lastCode = value;
                                lastCodeAt = now;
                                submit(value);
                            }
                        } else {
                            armed = true;
                        }
                    }
                } catch (e) { /* skip this frame, keep looping */ }
                rafId = requestAnimationFrame(scanFrame);
            }

            // `card` tints the whole result panel by outcome so a rejection is
            // unmistakable across the room, not just a small corner badge.
            const STATES = {
                recorded:     { bg: 'bg-emerald-500', icon: '✓', good: true,  card: 'border-emerald-500/40 bg-emerald-500/10' },
                done:         { bg: 'bg-brand-500',   icon: '★', good: true,  card: 'border-brand-500/40 bg-brand-500/10' },
                too_soon:     { bg: 'bg-amber-500',   icon: '!', good: false, card: 'border-amber-500/50 bg-amber-500/10' },
                out_of_order: { bg: 'bg-amber-500',   icon: '!', good: false, card: 'border-amber-500/50 bg-amber-500/10' },
                no_set:       { bg: 'bg-amber-500',   icon: '!', good: false, card: 'border-amber-500/50 bg-amber-500/10' },
                inactive:     { bg: 'bg-amber-500',   icon: '!', good: false, card: 'border-amber-500/50 bg-amber-500/10' },
                not_intern:   { bg: 'bg-red-500',     icon: '✕', good: false, card: 'border-red-500/50 bg-red-500/10' },
                unknown_code: { bg: 'bg-red-500',     icon: '✕', good: false, card: 'border-red-500/50 bg-red-500/10' },
                error:        { bg: 'bg-red-500',     icon: '✕', good: false, card: 'border-red-500/50 bg-red-500/10' },
                session:      { bg: 'bg-gray-500',    icon: '⟳', good: false, card: 'border-white/10 bg-white/5' },
            };

            function show(el) {
                idle.classList.add('hidden');
                cameraPanel.classList.add('hidden');
                manualPanel.classList.add('hidden');
                processing.classList.add('hidden');
                result.classList.add('hidden');
                el.classList.remove('hidden');
            }

            function showProcessing() {
                clearTimeout(resetTimer);
                show(processing);
            }

            // Return to the current mode's "home" panel after a result.
            function resetToIdle() {
                if (mode === 'camera') {
                    show(cameraPanel);
                    try { video.play(); } catch (e) {}
                } else if (mode === 'manual') {
                    show(manualPanel);
                    setTimeout(() => { try { studentIdInput.focus(); } catch (e) {} }, 60);
                } else {
                    show(idle);
                    focusInput();
                    refreshScanStatus();
                }
            }

            function esc(s) {
                return String(s == null ? '' : s)
                    .replace(/&/g, '&amp;').replace(/</g, '&lt;')
                    .replace(/>/g, '&gt;').replace(/\"/g, '&quot;');
            }

            function titleFor(data) {
                switch (data.state) {
                    case 'recorded': return /out( \(2\))?$/i.test(data.action || '') ? 'Successfully timed out' : 'Successfully timed in';
                    case 'done': return 'All done for today';
                    case 'too_soon': return 'Too soon — wait before scanning again';
                    case 'out_of_order': return "That time doesn't fit";
                    case 'no_set': return 'No active OJT set';
                    case 'inactive': return 'Account deactivated';
                    case 'not_intern': return "Not an intern's QR";
                    case 'unknown_code': return 'Student ID not found';
                    case 'session': return 'Session ended';
                    default: return 'Something went wrong';
                }
            }

            function subtitleFor(data) {
                if (data.state === 'recorded') { return esc(data.action) + ' · ' + esc(data.recordedAt); }
                return esc(data.message || '');
            }

            function timesGrid(log) {
                if (!log) { return ''; }
                const cell = (label, val) =>
                    '<div class="flex items-center justify-between"><span class="text-gray-400">' + label + '</span>' +
                    '<span class="font-medium tabular-nums ' + (val ? '' : 'text-gray-600') + '">' + (val ? esc(val) : '—') + '</span></div>';
                let hours = '';
                if (Number(log.hoursToday) > 0) {
                    hours = '<div class="mt-3 pt-3 border-t border-white/10 flex items-center justify-between">' +
                        '<span class="text-gray-400 text-sm">Hours today</span>' +
                        '<span class="font-semibold tabular-nums">' + esc(log.hoursToday) + 'h</span></div>';
                }
                // Stepped-out gap pair per session — shown as extra rows only
                // on days the intern actually stepped out and came back.
                const gapCell = (label, val) =>
                    '<div class="flex items-center justify-between"><span class="text-gray-500">' + label + '</span>' +
                    '<span class="font-medium tabular-nums text-gray-300">' + (val ? esc(val) : '—') + '</span></div>';
                let gaps = '';
                if (log.amIn2 || log.amOut2 || log.pmIn2 || log.pmOut2) {
                    gaps = '<div class="mt-2 pt-2 border-t border-white/10 grid grid-cols-2 gap-x-4 gap-y-2">' +
                        gapCell('Back in (AM)', log.amIn2) + gapCell('Out again (AM)', log.amOut2) +
                        gapCell('Back in (PM)', log.pmIn2) + gapCell('Out again (PM)', log.pmOut2) +
                        '</div>';
                }
                return '<div class="mt-5 rounded-xl bg-gray-900/60 border border-white/10 px-4 py-4 text-sm">' +
                    '<div class="grid grid-cols-2 gap-x-4 gap-y-2">' +
                    cell('AM In', log.amIn) + cell('AM Out', log.amOut) +
                    cell('PM In', log.pmIn) + cell('PM Out', log.pmOut) +
                    '</div>' + gaps + hours + '</div>';
            }

            function progressBar(p) {
                if (!p || !p.target) { return ''; }
                const pct = Math.min(Number(p.percent) || 0, 100);
                return '<div class="mt-4 text-left">' +
                    '<div class="flex items-center justify-between text-xs text-gray-400 mb-1.5">' +
                    '<span>OJT progress</span><span class="font-medium text-gray-200 tabular-nums">' +
                    esc(p.accumulated) + 'h / ' + esc(p.target) + 'h</span></div>' +
                    '<div class="h-1.5 rounded-full bg-white/10 overflow-hidden">' +
                    '<div class="h-full rounded-full bg-brand-500" style="width:' + pct + '%"></div></div></div>';
            }

            function avatar(intern) {
                if (!intern) { return ''; }
                if (intern.avatarUrl) {
                    return '<img src="' + esc(intern.avatarUrl) + '" alt="" class="w-24 h-24 rounded-full object-cover ring-2 ring-white/20">';
                }
                return '<div class="w-24 h-24 rounded-full bg-white/10 text-white flex items-center justify-center text-3xl font-semibold">' +
                    esc(intern.initials || '•') + '</div>';
            }

            function showResult(data) {
                const s = STATES[data.state] || STATES.error;
                const intern = data.intern || null;
                const showDetails = ['recorded', 'done', 'out_of_order', 'too_soon'].includes(data.state);

                let head;
                if (intern) {
                    head =
                        '<div class="relative inline-block mb-5">' +
                        avatar(intern) +
                        '<span class="absolute -bottom-1 -right-1 w-8 h-8 rounded-full ' + s.bg + ' text-white flex items-center justify-center text-base font-bold ring-4 ring-gray-900">' +
                        s.icon + '</span></div>';
                } else {
                    head =
                        '<div class="w-20 h-20 rounded-full ' + s.bg + ' text-white flex items-center justify-center text-4xl font-bold mx-auto mb-5 shadow-lg">' +
                        s.icon + '</div>';
                }

                const name = intern
                    ? '<p class="text-sm text-gray-400 mb-1">' + esc(intern.name) +
                      (intern.studentId ? ' · ' + esc(intern.studentId) : '') + '</p>'
                    : '';

                // The face captured at scan time — the supervisor's proof of
                // who actually stood at the kiosk.
                const capture = (data.state === 'recorded' || data.state === 'done') && data.captureUrl
                    ? '<img src="' + esc(data.captureUrl) + '" alt="Verification photo" title="Captured at scan time"' +
                      ' class="mt-4 w-24 h-24 rounded-2xl object-cover ring-2 ring-white/20 mx-auto">' +
                      '<p class="text-[10px] uppercase tracking-widest text-gray-500 mt-2">Verification photo</p>'
                    : '';

                result.innerHTML =
                    '<div class="fade-in border ' + s.card + ' rounded-3xl px-6 py-8 text-center">' +
                    head + name +
                    '<h1 class="text-2xl font-bold tracking-tight">' + titleFor(data) + '</h1>' +
                    '<p class="text-gray-400 mt-1.5">' + subtitleFor(data) + '</p>' +
                    capture +
                    (data.note ? '<p class="mt-3 text-[13px] leading-relaxed text-gray-300">' + esc(data.note) + '</p>' : '') +
                    (showDetails ? timesGrid(data.log) + progressBar(data.progress) : '') +
                    '</div>';

                show(result);
                beep(s.good);

                // A time went on the books — move the intern to the top of
                // the duty logbook panel with their newest punch.
                if (data.state === 'recorded') { updateStationLog(data); }

                const delay = s.good ? 3000 : 2000;
                resetTimer = setTimeout(resetToIdle, delay);
            }

            // The duty logbook table: the intern's row re-inserts at its
            // alphabetical position with the four slot times rebuilt from
            // the scan response, the newest punch highlighted. A "(2)"
            // step-out time renders beneath its column's spine time —
            // mirrors the server-side table.
            function slotCell(main, second, activeMain, activeSecond) {
                var html = '<span class="' + (activeMain ? 'text-brand-300 font-medium' : (main ? 'text-gray-300' : '')) + '">' +
                    (main ? esc(main) : '<span class="text-gray-600">—</span>') + '</span>';
                if (second) {
                    html += '<div class="text-[10px] leading-tight ' + (activeSecond ? 'text-brand-300 font-medium' : 'text-gray-500') + '">(2) ' + esc(second) + '</div>';
                }
                return '<td class="px-2 py-2 tabular-nums whitespace-nowrap align-top">' + html + '</td>';
            }

            function updateStationLog(data) {
                var body = document.getElementById('stationLog');
                if (!body || !data.intern || !data.log) { return; }

                var empty = document.getElementById('stationLogEmpty');
                if (empty) { empty.remove(); }

                var existing = body.querySelector('[data-user="' + data.intern.id + '"]');
                if (existing) { existing.remove(); }

                var row = document.createElement('tr');
                row.dataset.user = data.intern.id;
                row.className = 'fade-in';
                row.innerHTML =
                    '<td class="px-3 py-2 text-white truncate max-w-[9rem]" title="' + esc(data.intern.name) + '">' + esc(data.intern.shortName) + '</td>' +
                    slotCell(data.log.amIn, data.log.amIn2, data.slot === 'am_time_in', data.slot === 'am_time_in_2') +
                    slotCell(data.log.amOut, data.log.amOut2, data.slot === 'am_time_out', data.slot === 'am_time_out_2') +
                    slotCell(data.log.pmIn, data.log.pmIn2, data.slot === 'pm_time_in', data.slot === 'pm_time_in_2') +
                    slotCell(data.log.pmOut, data.log.pmOut2, data.slot === 'pm_time_out', data.slot === 'pm_time_out_2');

                // Keep the table alphabetical — drop the row in at its name's
                // position rather than the top.
                var rows = Array.from(body.children);
                rows.push(row);
                rows.sort(function (a, b) {
                    return a.querySelector('td').textContent.trim()
                        .localeCompare(b.querySelector('td').textContent.trim(), 'en', { sensitivity: 'base' });
                });
                rows.forEach(function (r) { body.appendChild(r); });

                var count = document.getElementById('stationLogCount');
                if (count) { count.textContent = body.children.length; }
            }

            function showSession() {
                showResult({
                    state: 'session',
                    message: 'The admin session on this computer expired. Sign in again to keep the station running.',
                });
                clearTimeout(resetTimer);
                resetTimer = setTimeout(() => { window.location.href = "{{ route('admin.kiosk.index') }}"; }, 3500);
            }

            // ---- shared POST (multipart, so it can carry the capture) ----
            async function send(url, form) {
                if (busy) { return; }
                busy = true;
                showProcessing();
                try {
                    const res = await fetch(url, {
                        method: 'POST',
                        headers: {
                            'Accept': 'application/json',
                            'X-CSRF-TOKEN': csrf,
                            'X-Requested-With': 'XMLHttpRequest',
                        },
                        credentials: 'same-origin',
                        body: form,
                    });

                    if (res.redirected || res.status === 401 || res.status === 419) {
                        showSession();
                        return;
                    }

                    const data = await res.json();
                    showResult(data);
                } catch (e) {
                    showResult({ state: 'error', message: 'Could not reach the server. Check the connection and try again.' });
                } finally {
                    busy = false;
                }
            }

            function submit(code) { submitWithCapture(scanUrl, 'code', code); }
            function submitManual(studentId) { submitWithCapture(manualUrl, 'student_id', studentId); }

            // Grab the verification camera's frame at the instant of the scan
            // (mirrored preview stays CSS-only — the saved photo is unmirrored),
            // then post the scan as multipart. No camera → the scan posts
            // without a capture and everything else works as before.
            async function submitWithCapture(url, field, value) {
                const form = new FormData();
                form.append(field, value);
                try {
                    const shot = await captureFrame();
                    if (shot) { form.append('capture', shot, 'capture.jpg'); }
                } catch (e) { /* camera hiccup must never block a scan */ }
                send(url, form);
            }

            // ---- verification camera ----
            const guardVideo = document.getElementById('guardVideo');
            const guardCam = document.getElementById('guardCam');
            const guardDot = document.getElementById('guardDot');
            const guardNote = document.getElementById('guardNote');
            const guardCanvas = document.createElement('canvas');
            const guardCtx = guardCanvas.getContext('2d');
            let guardStream = null;

            async function startGuardCamera() {
                try {
                    if (!navigator.mediaDevices || !navigator.mediaDevices.getUserMedia) {
                        throw new Error('unsupported');
                    }
                    guardStream = await navigator.mediaDevices.getUserMedia({
                        video: { width: { ideal: 640 }, height: { ideal: 480 } },
                        audio: false,
                    });
                    guardVideo.srcObject = guardStream;
                    await guardVideo.play();
                } catch (e) {
                    guardStream = null;
                    guardDot.classList.replace('bg-emerald-400', 'bg-gray-500');
                    guardNote.textContent = 'No camera';
                }
            }

            // A JPEG frame from the live preview, or null when the camera
            // isn't producing frames. Kept small so a day of scans stays
            // a few MB on the server.
            async function captureFrame() {
                try {
                    if (!guardStream || guardVideo.readyState < 2 || !guardVideo.videoWidth) {
                        return null;
                    }
                    const width = 640;
                    const height = Math.round(guardVideo.videoHeight * (width / guardVideo.videoWidth));
                    guardCanvas.width = width;
                    guardCanvas.height = height;
                    guardCtx.drawImage(guardVideo, 0, 0, width, height);
                    return await new Promise((resolve) => guardCanvas.toBlob(resolve, 'image/jpeg', 0.85));
                } catch (e) {
                    return null;
                }
            }

            startGuardCamera();

            // ---- soft audio cue: rising tone on success, low tone otherwise ----
            let audioCtx = null;
            function beep(good) {
                try {
                    audioCtx = audioCtx || new (window.AudioContext || window.webkitAudioContext)();
                    const osc = audioCtx.createOscillator();
                    const gain = audioCtx.createGain();
                    osc.connect(gain); gain.connect(audioCtx.destination);
                    osc.type = 'sine';
                    osc.frequency.value = good ? 880 : 220;
                    gain.gain.setValueAtTime(0.0001, audioCtx.currentTime);
                    gain.gain.exponentialRampToValueAtTime(0.15, audioCtx.currentTime + 0.02);
                    gain.gain.exponentialRampToValueAtTime(0.0001, audioCtx.currentTime + 0.25);
                    osc.start();
                    osc.stop(audioCtx.currentTime + 0.26);
                } catch (e) {}
            }
        })();
    </script>
</body>
</html>
