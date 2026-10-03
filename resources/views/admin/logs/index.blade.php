@extends('layouts.app')

@section('title', 'Logbook')

@section('content')
<div class="mb-8">
    <h1 class="text-xl font-semibold tracking-tight text-gray-900">Logbook</h1>
    <p class="text-sm text-gray-500 mt-0.5">Daily time records — interns time in and out at the office kiosk; use New Entry only to fix or fill a missed scan.</p>
</div>

{{-- Toolbar: date navigator + New Entry --}}
<div class="flex flex-wrap items-center justify-between gap-3 mb-5">
    <div class="flex flex-wrap items-center gap-1.5">
        <a href="{{ route('admin.logs.index', ['date' => $prevDate]) }}" class="w-8 h-8 rounded-lg border border-gray-200 bg-white flex items-center justify-center hover:bg-gray-50 text-gray-500 transition" title="Previous day">
            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M15 18l-6-6 6-6"/></svg>
        </a>
        <div class="px-3.5 py-1.5 rounded-lg bg-white border border-gray-200">
            <span class="text-sm font-medium text-gray-900">{{ $date->format('l, F j, Y') }}</span>
        </div>
        <a href="{{ route('admin.logs.index', ['date' => $nextDate]) }}" class="w-8 h-8 rounded-lg border border-gray-200 bg-white flex items-center justify-center hover:bg-gray-50 text-gray-500 transition" title="Next day">
            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M9 18l6-6-6-6"/></svg>
        </a>
        @unless($isToday)
            <a href="{{ route('admin.logs.index') }}" class="text-sm font-medium text-brand-600 hover:underline ml-1.5">Today</a>
        @endunless

        {{-- Review status filter — narrows the day's entries by review state --}}
        <form method="GET" action="{{ route('admin.logs.index') }}" class="ml-2 pl-3 border-l border-gray-200">
            <input type="hidden" name="date" value="{{ $date->format('Y-m-d') }}">
            <select name="status" onchange="this.form.submit()" title="Filter by review status"
                class="px-2 py-1.5 rounded-lg border-gray-200 text-xs text-gray-600 focus:border-brand-400 focus:ring-2 focus:ring-brand-500/15 transition">
                <option value="" {{ $status === null ? 'selected' : '' }}>All review states</option>
                <option value="pending" {{ $status === 'pending' ? 'selected' : '' }}>Pending review</option>
                <option value="approved" {{ $status === 'approved' ? 'selected' : '' }}>Approved</option>
                <option value="rejected" {{ $status === 'rejected' ? 'selected' : '' }}>Rejected</option>
            </select>
        </form>

        {{-- CSV export for a date range (defaults to this month) --}}
        <form method="GET" action="{{ route('admin.logs.export') }}" class="flex items-center gap-1.5 ml-2 pl-3 border-l border-gray-200">
            <input type="date" name="from" value="{{ request('from', today()->startOfMonth()->format('Y-m-d')) }}" title="From"
                class="px-2 py-1.5 rounded-lg border-gray-200 text-xs text-gray-600 focus:border-brand-400 focus:ring-2 focus:ring-brand-500/15 transition">
            <span class="text-gray-300 text-xs">→</span>
            <input type="date" name="to" value="{{ request('to', today()->format('Y-m-d')) }}" title="To"
                class="px-2 py-1.5 rounded-lg border-gray-200 text-xs text-gray-600 focus:border-brand-400 focus:ring-2 focus:ring-brand-500/15 transition">
            <button type="submit" class="inline-flex items-center gap-1.5 bg-white border border-gray-200 hover:bg-gray-50 text-gray-700 text-xs font-medium px-2.5 py-1.5 rounded-lg transition">
                <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><path d="M7 10l5 5 5-5"/><path d="M12 15V3"/></svg>
                CSV
            </button>
        </form>
    </div>
    <button type="button" onclick="document.getElementById('newEntryModal').classList.remove('hidden')"
        class="inline-flex items-center gap-1.5 bg-brand-600 hover:bg-brand-700 text-white text-sm font-medium px-3.5 py-2 rounded-lg transition">
        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 5v14M5 12h14"/></svg>
        New Entry
    </button>
</div>

{{-- Entries table --}}
<div class="bg-white border border-gray-200 rounded-xl overflow-x-auto">
    @if($logs->isNotEmpty())
        <table class="w-full text-sm">
            <thead>
                <tr class="text-left text-[11px] font-medium uppercase tracking-widest text-gray-400 border-b border-gray-200">
                    <th class="px-5 py-3">Name</th>
                    <th class="px-4 py-3">AM In</th>
                    <th class="px-4 py-3">AM Out</th>
                    <th class="px-4 py-3">PM In</th>
                    <th class="px-4 py-3">PM Out</th>
                    <th class="px-4 py-3">
                        <a href="{{ route('admin.logs.index', array_filter(['date' => $date->format('Y-m-d'), 'sort' => $sort === 'hours_desc' ? 'hours_asc' : 'hours_desc'])) }}"
                           class="inline-flex items-center gap-1 hover:text-gray-700">
                            Hours
                            @if($sort === 'hours_asc') <span>↑</span>
                            @elseif($sort === 'hours_desc') <span>↓</span>
                            @else <span class="text-gray-300">↕</span>
                            @endif
                        </a>
                    </th>
                    <th class="px-4 py-3">Status</th>
                    <th class="px-4 py-3">Photo</th>
                    <th class="px-5 py-3 text-right">Actions</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-100">
                @php $t = fn($v) => $v ? \Illuminate\Support\Carbon::parse($v)->format('g:i A') : '—'; @endphp
                @foreach($logs as $log)
                    <tr class="hover:bg-gray-50/60 transition-colors">
                        <td class="px-5 py-3">
                            <a href="{{ route('admin.logs.show', $log->user) }}" class="font-medium text-gray-900 hover:text-brand-700">{{ $log->user->full_name }}</a>
                            <p class="text-xs text-gray-400 tabular-nums">{{ $log->user->student_id }}</p>
                        </td>
                        <td class="px-4 py-3 text-gray-500 tabular-nums whitespace-nowrap">
                            {{ $t($log->am_time_in) }}
                            @if($log->am_time_in_2)
                                <span class="block text-[10px] text-gray-400">↩ {{ $t($log->am_time_in_2) }}{{ $log->am_time_out_2 ? ' – '.$t($log->am_time_out_2) : '' }}</span>
                            @endif
                        </td>
                        <td class="px-4 py-3 text-gray-500 tabular-nums whitespace-nowrap">{{ $t($log->am_time_out) }}</td>
                        <td class="px-4 py-3 text-gray-500 tabular-nums whitespace-nowrap">
                            {{ $t($log->pm_time_in) }}
                            @if($log->pm_time_in_2)
                                <span class="block text-[10px] text-gray-400">↩ {{ $t($log->pm_time_in_2) }}{{ $log->pm_time_out_2 ? ' – '.$t($log->pm_time_out_2) : '' }}</span>
                            @endif
                        </td>
                        <td class="px-4 py-3 text-gray-500 tabular-nums whitespace-nowrap">{{ $t($log->pm_time_out) }}</td>
                        <td class="px-4 py-3 whitespace-nowrap">
                            <span class="font-medium text-gray-900 tabular-nums">{{ number_format($log->hours_rendered, 2) }}h</span>
                            @if($log->has_overtime)
                                <span class="block text-[10px] font-medium text-amber-600 tabular-nums">+{{ number_format($log->overtime_hours, 2) }}h OT</span>
                            @endif
                        </td>
                        <td class="px-4 py-3">
                            <span class="inline-flex items-center px-2 py-0.5 rounded-md text-xs font-medium {{ $log->attendance_status['class'] }}">{{ $log->attendance_status['label'] }}</span>
                            @if($log->status !== 'approved')
                                <span class="inline-flex items-center px-2 py-0.5 rounded-md text-xs font-medium ml-1 {{ $log->review_status['class'] }}"
                                      @if($log->review_comment) title="By {{ $log->reviewedBy?->full_name ?? '—' }}: {{ $log->review_comment }}" @endif
                                >{{ $log->review_status['label'] }}</span>
                            @endif
                        </td>
                        <td class="px-4 py-3">
                            @include('partials.log-photo', ['log' => $log])
                        </td>
                        <td class="px-5 py-3 text-right whitespace-nowrap">
                            <button type="button" onclick="document.getElementById('editModal{{ $log->id }}').classList.remove('hidden')"
                                class="inline-flex items-center justify-center w-7 h-7 rounded-md text-gray-400 hover:text-brand-600 hover:bg-brand-50 transition" title="Edit">
                                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 20h9"/><path d="M16.5 3.5a2.12 2.12 0 0 1 3 3L7 19l-4 1 1-4Z"/></svg>
                            </button>
                            <form method="POST" action="{{ route('admin.logs.destroy', $log) }}" class="inline"
                                  data-confirm-title="Delete entry"
                                  data-confirm-message="Delete {{ $log->user?->display_name ?? 'this intern' }}'s entry for {{ $log->date->format('M d, Y') }}? This cannot be undone."
                                  data-confirm-action="Delete"
                                  onsubmit="return askConfirm(this);">
                                @csrf
                                @method('DELETE')
                                <button class="inline-flex items-center justify-center w-7 h-7 rounded-md text-gray-400 hover:text-red-600 hover:bg-red-50 transition" title="Delete">
                                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M3 6h18"/><path d="M8 6V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2m3 0-1 14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2L4 6"/></svg>
                                </button>
                            </form>
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    @else
        <div class="flex flex-col items-center justify-center py-16 px-6 text-center">
            <p class="text-sm text-gray-400 mb-4">No logbook entries for this date.</p>
            <button type="button" onclick="document.getElementById('newEntryModal').classList.remove('hidden')"
                class="inline-flex items-center gap-1.5 bg-white border border-gray-200 hover:bg-gray-50 text-gray-700 text-sm font-medium px-3.5 py-2 rounded-lg transition">
                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 5v14M5 12h14"/></svg>
                Add First Entry
            </button>
        </div>
    @endif
</div>

@include('admin.logs.partials.new-entry-modal')

@foreach($logs as $log)
    @include('admin.logs.partials.edit-entry-modal', ['log' => $log])
@endforeach

@include('partials.confirm-modal')
@endsection
