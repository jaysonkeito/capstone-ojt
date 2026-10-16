@extends('layouts.app')

@section('title', 'Notifications')

@section('content')
<div>
<div class="flex flex-wrap items-end justify-between gap-3 mb-8">
    <div>
        <h1 class="text-xl font-semibold tracking-tight text-gray-900">Notifications</h1>
        <p class="text-sm text-gray-500 mt-0.5">Review decisions, pending entries, and flagged duty days land here.</p>
    </div>
    @if($notifications->whereNull('read_at')->isNotEmpty())
        <form method="POST" action="{{ route('notifications.read-all') }}">
            @csrf
            <button type="submit" class="inline-flex items-center gap-1.5 bg-white border border-gray-200 hover:bg-gray-50 text-gray-700 text-sm font-medium px-3 py-2 rounded-lg transition">
                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M20 6 9 17l-5-5"/></svg>
                Mark all as read
            </button>
        </form>
    @endif
</div>

<div class="bg-white border border-gray-200 rounded-xl divide-y divide-gray-100">
    @php
        $linkFor = function (array $data) {
            $role = auth()->user()->role;
            $internId = $data['intern_id'] ?? null;
            $kind = $data['kind'] ?? null;

            return match (true) {
                $kind === 'log_reviewed' => route('intern.dashboard'),
                $kind === 'timesheet_certified' => route('intern.dashboard'),
                $kind === 'attendance_request_decided' => route('intern.requests.index'),
                $kind === 'request_submitted' && $role === 'admin' => route('admin.requests.index'),
                $kind === 'log_flagged' && $role === 'supervisor' && $internId => route('monitor.intern', ['intern' => $internId]),
                $kind === 'log_flagged' && $role === 'admin' && $internId => route('admin.logs.show', ['intern' => $internId]),
                $kind === 'manual_entry_submitted' && $role === 'admin' && $internId => route('admin.logs.show', ['intern' => $internId]),
                $kind === 'attendance_request_submitted' && $role === 'supervisor' && $internId => route('monitor.intern', ['intern' => $internId]),
                $kind === 'attendance_request_submitted' => route('monitor.requests.index'),
                $kind === 'request_decided' => route('monitor.requests.index'),
                $kind === 'scan_recorded' && $role === 'intern' => route('intern.dashboard'),
                default => null,
            };
        };
        $summaryFor = function (array $data): array {
            $kind = $data['kind'] ?? null;
            $date = isset($data['date']) ? \Illuminate\Support\Carbon::parse($data['date'])->format('M d, Y') : '';
            $comment = $data['comment'] ?? null;

            return match ($kind) {
                'log_reviewed' => $data['decision'] === 'rejected'
                    ? ["Your duty entry for {$date} was rejected by {$data['reviewer']}.", $comment]
                    : ["Your duty entry for {$date} was approved by {$data['reviewer']}.", $comment],
                'manual_entry_submitted' => [
                    "{$data['supervisor']} logged a pending entry for {$data['intern']} on {$date}"
                        .(isset($data['hours']) ? ' ('.number_format((float) $data['hours'], 2).'h)' : '').'.',
                    $comment,
                ],
                'log_flagged' => [
                    "{$data['flagged_by']} flagged {$data['intern']}'s entry for {$date} for a second look.",
                    $comment,
                ],
                'timesheet_certified' => [
                    "Your hours for {$data['period']} were certified by {$data['certified_by']}.",
                    null,
                ],
                'request_submitted' => [
                    "{$data['coordinator']} requested {$data['subject']} for {$data['intern']}.",
                    null,
                ],
                'request_decided' => [
                    "Your {$data['request_kind']} request for {$data['intern']} was {$data['decision']} by {$data['decided_by']}.",
                    $comment,
                ],
                'attendance_request_submitted' => [
                    "{$data['intern']} filed ".($data['request_type'] === 'correction' ? 'a time correction' : 'an absence report')." for {$date}.",
                    $data['reason'] ?? null,
                ],
                'attendance_request_decided' => [
                    "Your ".($data['request_type'] === 'correction' ? 'correction' : 'absence report')." for {$date} was {$data['decision']} by {$data['decided_by']}.",
                    $comment,
                ],
                'scan_recorded' => [
                    ($data['slot'] ?? 'Time').' recorded at '.($data['recorded_at'] ?? '').' on '.$date.'.',
                    null,
                ],
                'intern_reported' => [
                    "{$data['reporter']} reported {$data['intern']}.",
                    $data['reason'] ?? null,
                ],
                default => ['You have a new notification.', null],
            };
        };
    @endphp

    @forelse($notifications as $notification)
        @php
            [$summary, $comment] = $summaryFor($notification->data);
            $link = $linkFor($notification->data);
            // The full-content panel shows every field of the notification,
            // labeled in plain words — click a row to read the whole thing.
            $fieldLabels = [
                'reviewer' => 'Reviewed by', 'decision' => 'Decision', 'comment' => 'Comment',
                'date' => 'Duty date', 'intern' => 'Intern', 'supervisor' => 'Supervisor',
                'hours' => 'Hours', 'flagged_by' => 'Flagged by', 'period' => 'Period',
                'certified_by' => 'Certified by', 'coordinator' => 'Coordinator',
                'subject' => 'Subject', 'request_kind' => 'Request type',
                'decided_by' => 'Decided by', 'reason' => 'Reason',
                'request_type' => 'Request type', 'slot' => 'Recorded slot',
                'recorded_at' => 'Recorded at', 'reporter' => 'Reported by',
            ];
            $details = collect($notification->data)->except(['kind', 'intern_id', 'capture']);
        @endphp
        {{-- Swipe row: the red delete zone sits behind the content, which
             slides left under a finger on phones. On wide screens the zone
             doesn't exist at all — the plain trash button does the job. --}}
        <div class="relative overflow-hidden">
            <form method="POST" action="{{ route('notifications.destroy', $notification) }}"
                class="md:hidden absolute inset-y-0 right-0 w-24 flex items-center justify-end pr-4 bg-red-50 border-l-4 border-red-500"
                data-confirm-title="Delete notification"
                data-confirm-message="Delete this notification permanently?"
                data-confirm-action="Delete"
                onsubmit="return askConfirm(this);">
                @csrf
                @method('DELETE')
                <span class="text-xs font-semibold text-red-600">Delete</span>
            </form>
            <div class="swipe-content relative px-5 py-4 flex items-start gap-3 cursor-pointer select-none {{ $notification->unread() ? 'bg-brand-50' : 'bg-white' }}"
                data-notification-row
                data-read-url="{{ $notification->unread() ? route('notifications.read', $notification) : '' }}">
                <span data-role="dot" class="mt-1 w-2 h-2 rounded-full shrink-0 {{ $notification->unread() ? 'bg-brand-500' : 'bg-gray-200' }}"></span>
                <div class="min-w-0 flex-1">
                    <p data-role="summary" class="text-sm text-gray-900 {{ $notification->unread() ? 'font-medium' : '' }}">{{ $summary }}</p>
                    @if($comment)
                        <p class="text-sm text-gray-500 mt-0.5 line-clamp-1">“{{ $comment }}”</p>
                    @endif
                    @if(($notification->data['kind'] ?? null) === 'scan_recorded' && ($notification->data['capture'] ?? null))
                        {{-- The kiosk's verification photo of the scan —
                             tappable to view, with a direct download. --}}
                        @php($captureUrl = \Illuminate\Support\Facades\Storage::disk('public')->url($notification->data['capture']))
                        <div class="flex items-center gap-2 mt-2">
                            <a href="{{ $captureUrl }}" target="_blank" rel="noopener">
                                <img src="{{ $captureUrl }}" loading="lazy" alt="Scan verification photo"
                                    class="w-14 h-14 rounded-lg object-cover border border-gray-200 hover:ring-2 hover:ring-brand-400 transition">
                            </a>
                            <a href="{{ $captureUrl }}" download
                                class="inline-flex items-center gap-1 text-[11px] font-medium text-brand-600 hover:underline">
                                <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 3v12m0 0 4-4m-4 4-4-4"/><path d="M5 21h14"/></svg>
                                Download photo
                            </a>
                        </div>
                    @endif
                    <p class="text-[11px] text-gray-400 mt-1">{{ $notification->created_at->diffForHumans() }} — click to view full content</p>
                </div>
                <div class="flex items-center gap-1.5 shrink-0">
                    @if($notification->unread())
                        <form data-role="mark-read" method="POST" action="{{ route('notifications.read', $notification) }}">
                            @csrf
                            <button type="submit" class="inline-flex items-center gap-1 text-xs font-medium text-gray-500 hover:text-brand-700 px-2 py-1.5 rounded-md hover:bg-brand-50 transition">
                                <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M20 6 9 17l-5-5"/></svg>
                                Mark read
                            </button>
                        </form>
                    @endif
                    <form method="POST" action="{{ route('notifications.destroy', $notification) }}"
                        data-confirm-title="Delete notification"
                        data-confirm-message="Delete this notification permanently?"
                        data-confirm-action="Delete"
                        onsubmit="return askConfirm(this);">
                        @csrf
                        @method('DELETE')
                        <button type="submit" class="hidden sm:inline-flex w-8 h-8 items-center justify-center rounded-md text-gray-500 hover:text-red-600 hover:bg-red-50 transition" title="Delete notification">
                            <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M3 6h18"/><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6"/><path d="M8 6V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"/></svg>
                        </button>
                    </form>
                    <svg data-role="chevron" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="text-gray-400 transition-transform mt-1"><path d="m6 9 6 6 6-6"/></svg>
                </div>
            </div>
            {{-- Full content — revealed by clicking the row. --}}
            <div data-notif-details class="hidden bg-gray-50 border-t border-gray-100 px-5 py-4">
                <p class="text-[10px] font-medium uppercase tracking-widest text-gray-400 mb-2.5">Full content</p>
                <dl class="space-y-1.5">
                    @foreach($details as $key => $value)
                        <div class="flex gap-3 text-sm">
                            <dt class="w-32 shrink-0 text-gray-500">{{ $fieldLabels[$key] ?? ucfirst(str_replace('_', ' ', $key)) }}</dt>
                            <dd class="text-gray-900 min-w-0 break-words">{{ is_array($value) ? implode(', ', $value) : (string) $value }}</dd>
                        </div>
                    @endforeach
                    <div class="flex gap-3 text-sm">
                        <dt class="w-32 shrink-0 text-gray-500">Received</dt>
                        <dd class="text-gray-900">{{ $notification->created_at->format('M d, Y g:i A') }}</dd>
                    </div>
                </dl>
                @if($link)
                    <a href="{{ $link }}" class="mt-3.5 inline-flex items-center gap-1.5 text-sm font-medium text-brand-600 hover:text-brand-700">
                        Open related page
                        <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M5 12h14"/><path d="m12 5 7 7-7 7"/></svg>
                    </a>
                @endif
            </div>
        </div>
    @empty
        <div class="px-5 py-16 text-center">
            <p class="text-sm text-gray-400">Nothing here yet — you're all caught up.</p>
        </div>
    @endforelse
</div>
</div>

@include('partials.confirm-modal')

@push('scripts')
<script>
// Swipe-left-to-delete on phones: the content slides over the red zone
// behind it; past a threshold it snaps open. One row open at a time, and
// a tap on the row snaps it closed. Wide screens just use the trash button.
(function () {
    if (!window.matchMedia('(max-width: 767px)').matches) { return; }

    var OPEN_X = -96;
    var openRow = null;

    function close(row) {
        row.style.transition = 'transform 0.15s ease';
        row.style.transform = '';
        if (openRow === row) { openRow = null; }
    }

    document.querySelectorAll('.swipe-content').forEach(function (row) {
        var startX = 0, startY = 0, dx = 0, horizontal = null;

        row.addEventListener('touchstart', function (e) {
            startX = e.touches[0].clientX;
            startY = e.touches[0].clientY;
            dx = 0;
            horizontal = null;
            row.style.transition = 'none';
        }, { passive: true });

        row.addEventListener('touchmove', function (e) {
            var dX = e.touches[0].clientX - startX;
            var dY = e.touches[0].clientY - startY;
            if (horizontal === null && (Math.abs(dX) > 6 || Math.abs(dY) > 6)) {
                horizontal = Math.abs(dX) > Math.abs(dY);
            }
            if (!horizontal) { return; }
            dx = Math.max(-112, Math.min(0, dX));
            row.style.transform = 'translateX(' + dx + 'px)';
        }, { passive: true });

        row.addEventListener('touchend', function () {
            row.style.transition = 'transform 0.15s ease';
            if (dx < -40) {
                row.style.transform = 'translateX(' + OPEN_X + 'px)';
                if (openRow && openRow !== row) { close(openRow); }
                openRow = row;
            } else {
                close(row);
            }
        });

        row.addEventListener('click', function () {
            if (openRow === row) { close(row); }
        });
    });
})();

// Click a notification to read its full content: the row expands into a
// details panel (every field, labeled) and — the first time an unread
// row is opened — quietly marks it read so the inbox stays honest.
(function () {
    document.querySelectorAll('[data-notification-row]').forEach(function (row) {
        row.addEventListener('click', function (e) {
            // Buttons, links, and forms keep their own behavior.
            if (e.target.closest('button, a, form, input, img')) { return; }

            // A swipe-open row closes on tap instead — let that handler win.
            if (row.style.transform) { return; }

            var panel = row.parentElement.querySelector('[data-notif-details]');
            var chevron = row.querySelector('[data-role="chevron"]');
            if (! panel) { return; }

            var expanding = panel.classList.contains('hidden');
            panel.classList.toggle('hidden');
            if (chevron) { chevron.classList.toggle('rotate-180', expanding); }

            if (expanding && row.dataset.readUrl) {
                markRead(row);
            }
        });
    });

    function markRead(row) {
        var url = row.dataset.readUrl;
        row.dataset.readUrl = ''; // once only, even if the request is slow

        fetch(url, {
            method: 'POST',
            headers: {
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                'X-Requested-With': 'XMLHttpRequest',
                'Accept': 'application/json',
            },
        }).catch(function () { /* read-state syncs on next full load anyway */ });

        row.classList.replace('bg-brand-50', 'bg-white');

        var dot = row.querySelector('[data-role="dot"]');
        if (dot) { dot.classList.replace('bg-brand-500', 'bg-gray-200'); }

        var summary = row.querySelector('[data-role="summary"]');
        if (summary) { summary.classList.remove('font-medium'); }

        var markReadForm = row.querySelector('[data-role="mark-read"]');
        if (markReadForm) { markReadForm.remove(); }
    }
})();
</script>
@endpush
@endsection
