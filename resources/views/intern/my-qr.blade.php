@extends('layouts.app')

@section('title', 'My QR Code')

@section('content')
<div class="max-w-xl mx-auto">

    <div class="mb-6">
        <h1 class="text-xl font-semibold tracking-tight text-gray-900">My QR Code</h1>
        <p class="text-sm text-gray-500 mt-0.5">Your personal time-in / time-out code. Show it to the scanner at the office to clock in and out.</p>
    </div>

    {{-- One code, one card: the ID badge is both what you hold up to the desk
         scanner and what you download. The QR is sized large enough to read
         straight off the phone screen. --}}
    <div class="bg-white border border-gray-200 rounded-2xl p-6 sm:p-8 flex flex-col items-center text-center">
        @include('intern.partials.qr-badge', ['intern' => $intern, 'qrDataUri' => $qrDataUri])

        <p class="mt-6 text-sm text-gray-500 leading-relaxed max-w-xs">
            Hold this up to the scanner window. Each scan records the next time of your day — <span class="font-medium text-gray-700">AM In → AM Out → PM In → PM Out</span>.
        </p>

        <button type="button" onclick="downloadBadge(this)"
            class="mt-6 w-full sm:w-auto inline-flex items-center justify-center gap-1.5 bg-brand-600 hover:bg-brand-700 disabled:opacity-60 text-white text-sm font-medium px-5 py-2.5 rounded-lg transition">
            <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><path d="M7 10l5 5 5-5"/><path d="M12 15V3"/></svg>
            Download PNG
        </button>

        <p class="mt-4 inline-flex items-center gap-2 text-[11px] text-gray-400">
            <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="11" width="18" height="11" rx="2"/><path d="M7 11V7a5 5 0 0 1 10 0v4"/></svg>
            This code is yours alone — don't share a photo of it with anyone.
        </p>
    </div>

    <div class="rounded-2xl bg-white border border-gray-200 p-6 mt-6">
        <h2 class="text-sm font-semibold text-gray-900 mb-3">How it works</h2>
        <ol class="space-y-3 text-sm text-gray-600">
            <li class="flex gap-3">
                <span class="shrink-0 w-5 h-5 rounded-full bg-brand-50 text-brand-700 text-[11px] font-semibold flex items-center justify-center">1</span>
                <span>At the office, open this page on your phone <span class="text-gray-400">(or bring your saved card).</span></span>
            </li>
            <li class="flex gap-3">
                <span class="shrink-0 w-5 h-5 rounded-full bg-brand-50 text-brand-700 text-[11px] font-semibold flex items-center justify-center">2</span>
                <span>Hold the code up to the desk scanner. It beeps and shows your name.</span>
            </li>
            <li class="flex gap-3">
                <span class="shrink-0 w-5 h-5 rounded-full bg-brand-50 text-brand-700 text-[11px] font-semibold flex items-center justify-center">3</span>
                <span>Scan again when you leave for lunch, return, and clock out — four scans a full day.</span>
            </li>
        </ol>
        <p class="mt-4 pt-4 border-t border-gray-100 text-xs text-gray-400 leading-relaxed">
            Forgot your QR? The front desk can look you up by Student ID to time you in.
        </p>
    </div>

</div>
@endsection

@push('scripts')
<script>
(function () {
    // Everything the canvas needs, handed over from the server. The QR is a
    // base64 PNG data URI (never taints the canvas); the avatar may be absent
    // or cross-origin, so we fall back to initials if it can't be drawn.
    const BADGE = {
        qr:        @json($qrDataUri),
        name:      @json($intern->full_name),
        studentId: @json($intern->student_id),
        initials:  @json($intern->initials),
        avatarUrl: @json($intern->avatar_url),
        fileName:  @json('OJT-ID-'.($intern->student_id ?: \Illuminate\Support\Str::slug($intern->full_name ?: 'intern')).'.png'),
    };

    const FONT = 'ui-sans-serif, system-ui, -apple-system, "Segoe UI", Roboto, Helvetica, Arial, sans-serif';

    function loadImage(src, cors) {
        return new Promise(function (resolve, reject) {
            const img = new Image();
            if (cors) { img.crossOrigin = 'anonymous'; }
            img.onload = function () { resolve(img); };
            img.onerror = reject;
            img.src = src;
        });
    }

    function roundRect(ctx, x, y, w, h, r) {
        ctx.beginPath();
        ctx.moveTo(x + r, y);
        ctx.arcTo(x + w, y,     x + w, y + h, r);
        ctx.arcTo(x + w, y + h, x,     y + h, r);
        ctx.arcTo(x,     y + h, x,     y,     r);
        ctx.arcTo(x,     y,     x + w, y,     r);
        ctx.closePath();
    }

    // Fixed vertical rhythm for the badge, mirroring the on-screen partial
    // (288px card, 64px avatar, 208px QR).
    function layout(hasId) {
        const L = { W: 288, headerH: 64, avatarD: 64, qrBox: 224, qrImg: 208 };
        let y = L.headerH + 22;
        L.avatarTop = y;            y += L.avatarD + 18;
        L.nameTop = y;              y += 15 + 6;
        if (hasId) { L.idTop = y;   y += 11; }
        y += 16;
        L.qrBoxTop = y;             y += L.qrBox + 16;
        L.footerTop = y;            y += 9 + 24;
        L.totalH = y;
        return L;
    }

    async function buildCanvas(useAvatar) {
        const qrImg = await loadImage(BADGE.qr, false);
        let avatarImg = null;
        if (useAvatar && BADGE.avatarUrl) {
            avatarImg = await loadImage(BADGE.avatarUrl, true);
        }

        const hasId = !!BADGE.studentId;
        const L = layout(hasId);
        const SCALE = 3; // crisp on retina and when printed
        const cx = L.W / 2;

        const canvas = document.createElement('canvas');
        canvas.width = L.W * SCALE;
        canvas.height = L.totalH * SCALE;
        const ctx = canvas.getContext('2d');
        ctx.scale(SCALE, SCALE);
        ctx.textAlign = 'center';

        // White card, clipped to rounded corners so the dark header tucks in.
        roundRect(ctx, 0, 0, L.W, L.totalH, 16);
        ctx.save();
        ctx.fillStyle = '#ffffff';
        ctx.fill();
        ctx.clip();

        // Header band (gray-900)
        ctx.fillStyle = '#111827';
        ctx.fillRect(0, 0, L.W, L.headerH);

        ctx.textBaseline = 'middle';
        ctx.fillStyle = '#9ca3af';
        ctx.font = '600 8px ' + FONT;
        try { ctx.letterSpacing = '1.5px'; } catch (e) {}
        ctx.fillText('NORSU CAS — OJT TRACKER', cx, 25);
        try { ctx.letterSpacing = '0px'; } catch (e) {}

        ctx.fillStyle = '#ffffff';
        ctx.font = '600 14px ' + FONT;
        ctx.fillText('Time In / Out', cx, 44);

        // Avatar — image cover-fit, or initials fallback (gray-100 / gray-500).
        const r = L.avatarD / 2, acy = L.avatarTop + r;
        ctx.save();
        ctx.beginPath();
        ctx.arc(cx, acy, r, 0, Math.PI * 2);
        ctx.closePath();
        ctx.clip();
        if (avatarImg) {
            const s = Math.max(L.avatarD / avatarImg.width, L.avatarD / avatarImg.height);
            const dw = avatarImg.width * s, dh = avatarImg.height * s;
            ctx.drawImage(avatarImg, cx - dw / 2, acy - dh / 2, dw, dh);
        } else {
            ctx.fillStyle = '#f3f4f6';
            ctx.fillRect(cx - r, acy - r, L.avatarD, L.avatarD);
            ctx.fillStyle = '#6b7280';
            ctx.textBaseline = 'middle';
            ctx.font = '600 22px ' + FONT;
            ctx.fillText(BADGE.initials || '', cx, acy + 1);
        }
        ctx.restore();
        ctx.beginPath();
        ctx.arc(cx, acy, r, 0, Math.PI * 2);
        ctx.lineWidth = 1;
        ctx.strokeStyle = '#e5e7eb';
        ctx.stroke();

        // Name
        ctx.textBaseline = 'top';
        ctx.fillStyle = '#111827';
        ctx.font = '600 15px ' + FONT;
        ctx.fillText(BADGE.name, cx, L.nameTop, L.W - 28);

        // Student ID (optional)
        if (hasId) {
            ctx.fillStyle = '#6b7280';
            ctx.font = '400 11px ' + FONT;
            ctx.fillText(BADGE.studentId, cx, L.idTop, L.W - 28);
        }

        // QR in a bordered white box
        const boxX = cx - L.qrBox / 2;
        roundRect(ctx, boxX, L.qrBoxTop, L.qrBox, L.qrBox, 12);
        ctx.fillStyle = '#ffffff';
        ctx.fill();
        ctx.lineWidth = 1;
        ctx.strokeStyle = '#f3f4f6';
        ctx.stroke();
        const qd = L.qrImg, qx = cx - qd / 2, qy = L.qrBoxTop + (L.qrBox - qd) / 2;
        ctx.drawImage(qrImg, qx, qy, qd, qd);

        // Footer
        ctx.fillStyle = '#9ca3af';
        ctx.font = '600 9px ' + FONT;
        try { ctx.letterSpacing = '1px'; } catch (e) {}
        ctx.fillText('PRESENT AT THE OFFICE SCANNER', cx, L.footerTop, L.W - 20);
        try { ctx.letterSpacing = '0px'; } catch (e) {}

        ctx.restore(); // drop the rounded clip

        // Outer border (gray-200)
        roundRect(ctx, 0.5, 0.5, L.W - 1, L.totalH - 1, 16);
        ctx.lineWidth = 1;
        ctx.strokeStyle = '#e5e7eb';
        ctx.stroke();

        return canvas;
    }

    function exportCanvas(canvas) {
        return new Promise(function (resolve, reject) {
            // toBlob throws synchronously if the canvas was tainted by a
            // cross-origin avatar — let that reject so we can retry cleanly.
            try {
                canvas.toBlob(function (blob) {
                    if (!blob) { reject(new Error('no blob')); return; }
                    const url = URL.createObjectURL(blob);
                    const a = document.createElement('a');
                    a.href = url;
                    a.download = BADGE.fileName;
                    document.body.appendChild(a);
                    a.click();
                    a.remove();
                    setTimeout(function () { URL.revokeObjectURL(url); }, 1000);
                    resolve();
                }, 'image/png');
            } catch (e) { reject(e); }
        });
    }

    async function downloadBadge(btn) {
        if (btn) { btn.disabled = true; }
        try {
            try {
                await exportCanvas(await buildCanvas(true));
            } catch (inner) {
                // Avatar was cross-origin / failed to load — rebuild with the
                // initials fallback so the download still succeeds.
                await exportCanvas(await buildCanvas(false));
            }
        } catch (e) {
            window.alert('Sorry — the ID card image could not be generated. Please try again.');
        } finally {
            if (btn) { btn.disabled = false; }
        }
    }

    window.downloadBadge = downloadBadge;
})();
</script>
@endpush
