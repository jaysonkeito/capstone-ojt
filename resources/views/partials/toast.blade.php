{{-- Toast notifications — auto-dismissing flash messages that float in the
     top-right corner and fade out after a few seconds, so they no longer
     push page content down or linger until the next navigation. Server
     flashes (session('status')) and validation errors ($errors) are
     rendered as toasts by the layout that includes this partial. --}}
@php
    // A small toast reads better than a big block: cap the message length.
    $toastMaxChars = 280;
@endphp

<div id="toastStack"
     class="fixed top-4 right-4 z-[60] flex w-[min(24rem,calc(100vw-2rem))] flex-col gap-2 pointer-events-none"
     aria-live="polite" aria-atomic="false">

    @if(session('status'))
        @php $toastSuccess = Str::limit(session('status'), $toastMaxChars); @endphp
        <div class="toast pointer-events-auto flex items-start gap-2.5 rounded-lg border border-emerald-100 bg-white px-4 py-3 text-sm text-emerald-800 shadow-lg shadow-emerald-900/5"
             data-toast-role="status" role="status">
            <svg class="mt-0.5 h-4 w-4 shrink-0 text-emerald-500" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M20 6 9 17l-5-5"/></svg>
            <p class="min-w-0 flex-1 break-words leading-relaxed">{{ $toastSuccess }}</p>
            <button type="button" class="toast-close -m-1 shrink-0 rounded p-1 text-emerald-400 transition hover:text-emerald-700" aria-label="Dismiss notification">
                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><path d="M18 6 6 18M6 6l12 12"/></svg>
            </button>
        </div>
    @endif

    @if($errors->any())
        <div class="toast pointer-events-auto flex items-start gap-2.5 rounded-lg border border-red-100 bg-white px-4 py-3 text-sm text-red-700 shadow-lg shadow-red-900/5"
             data-toast-role="error" role="alert">
            <svg class="mt-0.5 h-4 w-4 shrink-0 text-red-500" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="12" cy="12" r="9"/><path d="M12 8v4M12 16h.01"/></svg>
            <p class="min-w-0 flex-1 break-words leading-relaxed">
                @foreach($errors->all() as $error)
                    {{ $error }}@if(! $loop->last)<br>@endif
                @endforeach
            </p>
            <button type="button" class="toast-close -m-1 shrink-0 rounded p-1 text-red-400 transition hover:text-red-700" aria-label="Dismiss notification">
                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><path d="M18 6 6 18M6 6l12 12"/></svg>
            </button>
        </div>
    @endif
</div>

@once
    @push('scripts')
        <script>
            // Toast auto-dismiss: each toast hides itself a few seconds after
            // load — errors linger a little longer so they can be read. The
            // timer pauses while the pointer hovers the toast, and the close
            // button dismisses instantly.
            (function () {
                var TOAST_SUCCESS_MS = 4000; // success disappears quickly
                var TOAST_ERROR_MS = 7000;   // errors stay readable a bit longer

                function dismissToast(toast) {
                    toast.style.opacity = '0';
                    toast.style.transform = 'translateX(8px)';
                    // Wait for the fade to finish before freeing the space.
                    setTimeout(function () { toast.remove(); }, 200);
                }

                function armToast(toast) {
                    var lifetime = toast.dataset.toastRole === 'error'
                        ? TOAST_ERROR_MS
                        : TOAST_SUCCESS_MS;

                    var timer = setTimeout(function () { dismissToast(toast); }, lifetime);

                    // Hovering pauses the countdown; leaving resumes it.
                    toast.addEventListener('mouseenter', function () {
                        clearTimeout(timer);
                    });
                    toast.addEventListener('mouseleave', function () {
                        timer = setTimeout(function () { dismissToast(toast); }, lifetime);
                    });

                    var close = toast.querySelector('.toast-close');
                    if (close) {
                        close.addEventListener('click', function () {
                            clearTimeout(timer);
                            dismissToast(toast);
                        });
                    }
                }

                document.querySelectorAll('.toast').forEach(armToast);
            })();
        </script>
    @endpush
@endonce
