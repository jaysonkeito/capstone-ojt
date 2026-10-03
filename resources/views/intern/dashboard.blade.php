@extends('layouts.app')

@section('title', 'My OJT Dashboard')

@section('content')
<div class="flex flex-wrap items-center justify-between gap-3 mb-8">
    <div>
        <h1 class="text-xl font-semibold tracking-tight text-gray-900">Good {{ now()->hour < 12 ? 'morning' : (now()->hour < 17 ? 'afternoon' : 'evening') }}, {{ $intern->first_name }}</h1>
        <p class="text-sm text-gray-500 mt-0.5">{{ now()->format('l, F j, Y') }} · {{ $intern->ojt_track_label }}</p>
    </div>
</div>

{{-- Latest scan confirmation — mirrors on the intern's own screen the time
     their most recent scan recorded at the office desk scanner, so they still
     get a clear acknowledgement after the kiosk's result card clears. It's a
     "just scanned" toast, not an all-day status, so it only shows while the
     punch is recent (last 30 min) and then clears itself. --}}
@php($punch = $todayLog?->latest_punch)
@php($lunchNote = $punch && $punch['slot'] === 'pm_time_in'
    ? \App\Support\AttendanceRecorder::lunchWindowNote($punch['at'])
    : null)
@if($punch && $punch['at']->gte(now()->subMinutes(30)))
    <div class="rounded-xl bg-emerald-50 border border-emerald-200 px-5 py-4 mb-6 flex items-center gap-3">
        <span class="shrink-0 w-9 h-9 rounded-full bg-emerald-500 text-white flex items-center justify-center">
            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="M20 6 9 17l-5-5"/></svg>
        </span>
        <div>
            <p class="text-sm font-semibold text-emerald-900">Successfully timed {{ $punch['direction'] }} at {{ $punch['time'] }}</p>
            <p class="text-xs text-emerald-700 mt-0.5">{{ $punch['label'] }} recorded today.@switch($punch['slot'])
                @case('am_time_in') Don't forget to scan out for lunch. @break
                @case('am_time_out') Scan again when you're back for the afternoon. @break
                @case('pm_time_in') Don't forget to scan again when you leave. @break
                @case('pm_time_out') That's all four times — great work today! @break
                @case('am_time_in_2') Welcome back — scan again when you leave for lunch. @break
                @case('am_time_out_2') See you after lunch — scan again at the PM window. @break
                @case('pm_time_in_2') Welcome back — scan again when you leave. @break
                @case('pm_time_out_2') That's all for today — great work! @break
            @endswitch</p>
            @if($lunchNote)
                {{-- Back before the PM window formally opens — explain why the
                     scan landed on PM Time In, same wording as the kiosk card. --}}
                <p class="text-xs text-emerald-700 mt-0.5">{{ $lunchNote }}</p>
            @endif
        </div>
    </div>
@endif

{{-- How to time in/out — present your personal QR at the office desk scanner --}}
<div class="rounded-xl bg-white border border-gray-200 px-5 py-4 mb-6 flex flex-wrap items-center justify-between gap-3">
    <div class="flex items-start gap-3">
        <span class="mt-0.5 w-2 h-2 rounded-full bg-brand-500 shrink-0"></span>
        <div>
            <p class="text-sm font-medium text-gray-900">Time in / out at the office desk scanner</p>
            <p class="text-xs text-gray-500 mt-0.5">Present your personal QR code to the scanner at the front desk. Each scan records your next time of the day (AM In → AM Out → PM In → PM Out).</p>
        </div>
    </div>
    <a href="{{ route('intern.my-qr') }}"
        class="inline-flex items-center gap-1.5 bg-brand-600 hover:bg-brand-700 text-white text-sm font-medium px-3.5 py-2 rounded-lg transition whitespace-nowrap">
        <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="3" width="7" height="7" rx="1"/><rect x="14" y="3" width="7" height="7" rx="1"/><rect x="3" y="14" width="7" height="7" rx="1"/><path d="M14 14h3v3M21 21v.01M21 17v.01M17 21h.01M14 21v.01"/></svg>
        Show My QR Code
    </a>
</div>

{{-- Today's duty day — photo + notes upload unlocks after clock-out --}}
<div class="bg-white border border-gray-200 rounded-xl p-5 sm:p-6 mb-6">
    <div class="flex flex-wrap items-center justify-between gap-3 mb-4">
        <div>
            <h2 class="text-sm font-semibold text-gray-900">Today's Duty Day</h2>
            <p class="text-xs text-gray-400 mt-0.5">{{ now()->format('l, F j') }}</p>
        </div>
        @if($todayLog)
            <span class="inline-flex items-center gap-1.5 text-xs font-medium
                {{ $todayLog->has_journal ? 'text-emerald-700' : ($todayLog->clocked_out ? 'text-amber-700' : 'text-gray-500') }}">
                <span class="w-1.5 h-1.5 rounded-full {{ $todayLog->has_journal ? 'bg-emerald-500' : ($todayLog->clocked_out ? 'bg-amber-500' : 'bg-gray-300') }}"></span>
                {{ $todayLog->has_journal ? 'Complete' : ($todayLog->clocked_out ? 'Awaiting journal' : 'Clocked in') }}
            </span>
        @endif
    </div>

    @if($todayLog)
        <div class="flex flex-wrap gap-5 text-xs text-gray-500 mb-4 tabular-nums">
            <span>AM {{ $todayLog->am_time_in ? \Illuminate\Support\Carbon::parse($todayLog->am_time_in)->format('g:iA') : '—' }}–{{ $todayLog->am_time_out ? \Illuminate\Support\Carbon::parse($todayLog->am_time_out)->format('g:iA') : '—' }}</span>
            <span>PM {{ $todayLog->pm_time_in ? \Illuminate\Support\Carbon::parse($todayLog->pm_time_in)->format('g:iA') : '—' }}–{{ $todayLog->pm_time_out ? \Illuminate\Support\Carbon::parse($todayLog->pm_time_out)->format('g:iA') : '—' }}</span>
            <span class="font-medium text-gray-900">{{ number_format($todayLog->hours_rendered, 2) }}h rendered</span>
        </div>

        @if($todayLog->has_journal)
            <div class="flex items-start gap-4">
                <a href="{{ $todayLog->photo_url }}" target="_blank" title="View full photo">
                    <img src="{{ $todayLog->photo_url }}" alt="Duty photo" class="w-20 h-20 object-cover rounded-lg border border-gray-200">
                </a>
                <div class="flex-1 min-w-0">
                    <p class="text-sm text-gray-600 whitespace-pre-line">{{ $todayLog->notes ?: 'No notes for today.' }}</p>
                    <div class="mt-2 flex flex-wrap items-center gap-x-4 gap-y-1">
                        <a href="{{ route('intern.documentation') }}"
                            class="inline-flex items-center gap-1 text-xs font-medium text-brand-600 hover:underline">
                            Manage on My Journal →
                        </a>
                        <a href="{{ route('intern.report.show', $todayLog) }}"
                            class="inline-flex items-center gap-1 text-xs font-medium text-gray-500 hover:text-brand-600">
                            <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><path d="M14 2v6h6"/><path d="M12 18v-6M9 15l3 3 3-3"/></svg>
                            Export Daily Report
                        </a>
                    </div>
                </div>
            </div>
        @elseif($todayLog->clocked_out)
            <div class="rounded-lg bg-amber-50 border border-amber-100 px-4 py-3 text-sm text-amber-800 mb-4 flex flex-wrap items-center justify-between gap-2">
                <span>Upload your daily journal (photo + message) to complete today's entry.</span>
                <a href="{{ route('intern.documentation') }}" class="text-xs font-semibold text-brand-700 hover:underline whitespace-nowrap">
                    Upload on My Journal →
                </a>
            </div>
        @else
            <p class="text-sm text-gray-400">Your journal upload unlocks once your AM or PM clock-out is recorded — see My Journal.</p>
        @endif
    @else
        <p class="text-sm text-gray-400">No duty logged today yet — present your QR code at the office scanner to time in.</p>
    @endif
</div>

{{-- Stats — hairline-divided numbers --}}
<div class="grid grid-cols-3 divide-x divide-gray-200 border-y border-gray-200 mb-8">
    <div class="py-5 pr-4">
        <p class="text-[11px] font-medium uppercase tracking-widest text-gray-400">Accumulated</p>
        <p class="text-3xl font-semibold tracking-tight text-gray-900 mt-1.5 tabular-nums">{{ number_format($intern->accumulated_hours, 1) }}<span class="text-sm text-gray-400 font-normal ml-1">h</span></p>
    </div>
    <div class="py-5 px-4">
        <p class="text-[11px] font-medium uppercase tracking-widest text-gray-400">Remaining</p>
        <p class="text-3xl font-semibold tracking-tight {{ $intern->is_complete ? 'text-emerald-600' : 'text-gray-900' }} mt-1.5 tabular-nums">{{ number_format(max($intern->hours_remaining, 0), 1) }}<span class="text-sm text-gray-400 font-normal ml-1">h</span></p>
    </div>
    <div class="py-5 pl-4">
        <p class="text-[11px] font-medium uppercase tracking-widest text-gray-400">Target</p>
        <p class="text-3xl font-semibold tracking-tight text-gray-900 mt-1.5 tabular-nums">{{ $intern->target_hours }}<span class="text-sm text-gray-400 font-normal ml-1">h</span></p>
    </div>
</div>

{{-- Progress bar --}}
<div class="mb-8">
    <div class="flex justify-between items-baseline mb-2">
        <span class="text-sm font-medium text-gray-700">Overall Progress</span>
        <span class="text-sm font-semibold tabular-nums {{ $intern->is_complete ? 'text-emerald-600' : 'text-gray-900' }}">
            {{ $intern->completion_percentage }}%
            @if($intern->is_complete) — Completed 🎉 @endif
        </span>
    </div>
    <div class="w-full bg-gray-100 rounded-full h-2">
        <div class="h-2 rounded-full transition-all {{ $intern->is_complete ? 'bg-emerald-500' : 'bg-brand-500' }}"
             style="width: {{ min($intern->completion_percentage, 100) }}%"></div>
    </div>
</div>

<div class="grid grid-cols-1 lg:grid-cols-5 gap-6">
    {{-- Duty calendar --}}
    <div class="lg:col-span-3 bg-white border border-gray-200 rounded-xl p-5">
        <div class="flex items-center justify-between mb-4">
            <a href="{{ route('intern.dashboard', ['month' => $prevMonth]) }}" class="text-gray-300 hover:text-gray-700 px-2">&larr;</a>
            <h2 class="text-sm font-semibold text-gray-900">{{ $month->format('F Y') }}</h2>
            <a href="{{ route('intern.dashboard', ['month' => $nextMonth]) }}" class="text-gray-300 hover:text-gray-700 px-2">&rarr;</a>
        </div>

        <div class="grid grid-cols-7 gap-1 text-center text-[10px] font-medium uppercase tracking-wider text-gray-400 mb-1">
            <div>Sun</div><div>Mon</div><div>Tue</div><div>Wed</div><div>Thu</div><div>Fri</div><div>Sat</div>
        </div>

        @foreach($calendarWeeks as $week)
            <div class="grid grid-cols-7 gap-1 mb-1">
                @foreach($week as $day)
                    <div class="aspect-square rounded-md flex flex-col items-center justify-center text-xs
                        {{ $day['in_month'] ? 'bg-gray-50' : 'bg-transparent text-gray-200' }}
                        {{ $day['log'] ? 'bg-brand-50 border border-brand-200' : '' }}"
                        title="{{ $day['log']?->notes }}">
                        <span class="{{ $day['log'] ? 'text-brand-700 font-semibold' : ($day['in_month'] ? 'text-gray-500' : 'text-gray-200') }} tabular-nums">
                            {{ $day['date']->day }}
                        </span>
                        @if($day['log'])
                            <span class="text-[9px] text-brand-500 tabular-nums">{{ number_format($day['log']->hours_rendered, 1) }}h</span>
                        @endif
                    </div>
                @endforeach
            </div>
        @endforeach

        <div class="flex items-center gap-2 mt-3 text-xs text-gray-400">
            <span class="w-2.5 h-2.5 rounded bg-brand-50 border border-brand-200 inline-block"></span> Duty day logged
        </div>
    </div>

    {{-- History list --}}
    <div class="lg:col-span-2 bg-white border border-gray-200 rounded-xl overflow-hidden">
        <div class="px-5 py-3.5 border-b border-gray-100 flex items-center justify-between">
            <h2 class="text-sm font-semibold text-gray-900">Log History</h2>
            <div class="flex items-center gap-3">
                <a href="{{ route('intern.weekly-report') }}" target="_blank" rel="noopener"
                   class="inline-flex items-center gap-1.5 text-xs font-medium text-brand-600 hover:underline">
                    <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><path d="M14 2v6h6"/><path d="M9 13h6M9 17h4"/></svg>
                    Weekly Report
                </a>
                <a href="{{ route('intern.time-frame') }}"
                   class="inline-flex items-center gap-1.5 text-xs font-medium text-brand-600 hover:underline">
                    <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><path d="M14 2v6h6"/><path d="M9 13h6M9 17h4"/></svg>
                    Time Frame
                </a>
            </div>
        </div>
        <div class="max-h-[420px] overflow-y-auto divide-y divide-gray-100">
            @forelse($logs as $log)
                <div class="px-5 py-3">
                    <div class="flex justify-between items-baseline">
                        <span class="text-sm text-gray-900">{{ $log->date->format('M d, Y') }}</span>
                        <span class="flex items-center gap-1.5">
                            <span class="text-sm text-gray-900 font-medium tabular-nums">{{ number_format($log->hours_rendered, 2) }}h</span>
                            @if($log->has_overtime)
                                <span class="inline-flex items-center px-1.5 py-0.5 rounded text-[10px] font-medium bg-amber-50 text-amber-700 tabular-nums">+{{ number_format($log->overtime_hours, 2) }}h OT</span>
                            @endif
                        </span>
                    </div>
                    @if($log->notes)
                        <p class="text-xs text-gray-400 mt-0.5">{{ $log->notes }}</p>
                    @endif
                    @if($log->has_journal)
                        <div class="mt-1.5 flex flex-wrap items-center gap-2.5">
                            <a href="{{ $log->photo_url }}" target="_blank" title="View duty photo">
                                <img src="{{ $log->photo_url }}" alt="Duty photo" class="w-12 h-12 object-cover rounded-md border border-gray-200">
                            </a>
                            <a href="{{ route('intern.documentation') }}"
                                class="inline-flex items-center gap-1 text-[11px] font-medium text-gray-400 hover:text-brand-600">
                                Edit journal on My Journal →
                            </a>
                        </div>
                    @elseif($log->photo_required)
                        <a href="{{ route('intern.documentation') }}"
                            class="mt-1.5 inline-flex items-center gap-1 text-xs font-medium text-amber-600 hover:underline">
                            <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><path d="M17 8l-5-5-5 5"/><path d="M12 3v12"/></svg>
                            Upload Daily Journal on My Journal
                        </a>
                    @endif
                </div>
            @empty
                <p class="px-5 py-8 text-center text-gray-400 text-sm">No hours logged yet.</p>
            @endforelse
        </div>
    </div>
</div>

@include('partials.confirm-modal')

@if($pastEnrollments->isNotEmpty())
    <div class="mt-8">
        <h2 class="text-sm font-semibold text-gray-900 mb-3">Past OJT Sets</h2>
        <div class="bg-white border border-gray-200 rounded-xl divide-y divide-gray-100">
            @foreach($pastEnrollments as $set)
                <div class="px-5 py-4">
                    <div class="flex flex-wrap items-start justify-between gap-3 mb-3">
                        <div>
                            <p class="font-medium text-gray-900 text-sm">{{ $set->label }}</p>
                            @if($set->started_at)
                                <p class="text-xs text-gray-400 mt-0.5">
                                    {{ $set->started_at->format('M Y') }} – {{ $set->completed_at?->format('M Y') ?? 'ongoing' }}
                                </p>
                            @endif
                        </div>
                        <span class="inline-flex items-center gap-1.5 text-xs font-medium
                            {{ $set->status === 'completed' ? 'text-emerald-700' : ($set->status === 'active' ? 'text-brand-700' : 'text-gray-500') }}">
                            <span class="w-1.5 h-1.5 rounded-full {{ $set->status === 'completed' ? 'bg-emerald-500' : ($set->status === 'active' ? 'bg-brand-500' : 'bg-gray-300') }}"></span>
                            {{ $set->status_label }}
                        </span>
                    </div>
                    <div class="flex items-center gap-3">
                        <div class="flex-1 bg-gray-100 rounded-full h-1.5">
                            <div class="h-1.5 rounded-full {{ $set->is_complete ? 'bg-emerald-500' : 'bg-brand-500' }}" style="width: {{ min($set->completion_percentage, 100) }}%"></div>
                        </div>
                        <span class="text-xs text-gray-400 tabular-nums whitespace-nowrap">{{ number_format($set->accumulated_hours, 1) }} / {{ $set->target_hours }}h</span>
                    </div>
                </div>
            @endforeach
        </div>
    </div>
@endif
@endsection
