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
                default => ['You have a new notification.', null],
            };
        };
    @endphp

    @forelse($notifications as $notification)
        @php [$summary, $comment] = $summaryFor($notification->data); $link = $linkFor($notification->data); @endphp
        {{-- Swipe row: the red delete zone sits behind the content, which
             slides left under a finger on phones. On wide screens the plain
             trash button in the row does the same job. --}}
        <div class="relative overflow-hidden">
            <form method="POST" action="{{ route('notifications.destroy', $notification) }}"
                class="absolute inset-y-0 right-0 w-24 flex items-center justify-end pr-4 bg-red-50 border-l-4 border-red-500"
                data-confirm-title="Delete notification"
                data-confirm-message="Delete this notification permanently?"
                data-confirm-action="Delete"
                onsubmit="return askConfirm(this);">
                @csrf
                @method('DELETE')
                <span class="text-xs font-semibold text-red-600 sm:hidden">Delete</span>
            </form>
            <div class="swipe-content relative px-5 py-4 flex items-start gap-3 {{ $notification->unread() ? 'bg-brand-50/40' : 'bg-white' }}">
                <span class="mt-1 w-2 h-2 rounded-full shrink-0 {{ $notification->unread() ? 'bg-brand-500' : 'bg-gray-200' }}"></span>
                <div class="min-w-0 flex-1">
                    <p class="text-sm text-gray-900 {{ $notification->unread() ? 'font-medium' : '' }}">
                        @if($link)
                            <a href="{{ $link }}" class="hover:text-brand-700">{{ $summary }}</a>
                        @else
                            {{ $summary }}
                        @endif
                    </p>
                    @if($comment)
                        <p class="text-sm text-gray-500 mt-0.5">“{{ $comment }}”</p>
                    @endif
                    <p class="text-[11px] text-gray-400 mt-1">{{ $notification->created_at->diffForHumans() }}</p>
                </div>
                <div class="flex items-center gap-1 shrink-0">
                    @if($notification->unread())
                        <form method="POST" action="{{ route('notifications.read', $notification) }}">
                            @csrf
                            <button type="submit" class="text-xs font-medium text-gray-400 hover:text-gray-700 px-2 py-1 rounded-md hover:bg-gray-100 transition">Mark read</button>
                        </form>
                    @endif
                    <form method="POST" action="{{ route('notifications.destroy', $notification) }}"
                        data-confirm-title="Delete notification"
                        data-confirm-message="Delete this notification permanently?"
                        data-confirm-action="Delete"
                        onsubmit="return askConfirm(this);">
                        @csrf
                        @method('DELETE')
                        <button type="submit" class="hidden sm:inline-flex w-8 h-8 items-center justify-center rounded-md text-gray-300 hover:text-red-600 hover:bg-red-50 transition" title="Delete notification">
                            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M3 6h18"/><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6"/><path d="M8 6V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"/></svg>
                        </button>
                    </form>
                </div>
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
</script>
@endpush
@endsection
