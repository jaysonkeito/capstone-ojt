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
                default => ['You have a new notification.', null],
            };
        };
    @endphp

    @forelse($notifications as $notification)
        @php [$summary, $comment] = $summaryFor($notification->data); $link = $linkFor($notification->data); @endphp
        <div class="px-5 py-4 flex items-start gap-3 {{ $notification->unread() ? 'bg-brand-50/40' : '' }}">
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
            @if($notification->unread())
                <form method="POST" action="{{ route('notifications.read', $notification) }}">
                    @csrf
                    <button type="submit" class="text-xs font-medium text-gray-400 hover:text-gray-700 px-2 py-1 rounded-md hover:bg-gray-100 transition shrink-0">Mark read</button>
                </form>
            @endif
        </div>
    @empty
        <div class="px-5 py-16 text-center">
            <p class="text-sm text-gray-400">Nothing here yet — you're all caught up.</p>
        </div>
    @endforelse
</div>
</div>
@endsection
