@extends('layouts.app')

@section('title', 'Log History — ' . $intern->full_name)

@section('content')
<div class="flex flex-wrap items-end justify-between gap-3 mb-6">
    <div>
        <a href="{{ route('admin.interns.index') }}" class="text-xs font-medium text-gray-400 hover:text-gray-700">← Interns</a>
        <h1 class="text-xl font-semibold tracking-tight text-gray-900 mt-1">{{ $intern->full_name }}</h1>
        <p class="text-sm text-gray-500 mt-0.5">{{ $intern->student_id }} · {{ number_format($intern->accumulated_hours, 1) }} / {{ $intern->target_hours }} hours ({{ $intern->completion_percentage }}%)</p>
    </div>
    <div class="flex flex-wrap items-center gap-2">
        <a href="{{ route('admin.interns.timesheet', $intern) }}"
           class="inline-flex items-center gap-1.5 bg-white border border-gray-200 hover:bg-gray-50 text-gray-700 text-sm font-medium px-3.5 py-2 rounded-lg transition">
            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><path d="M14 2v6h6"/><path d="M8 13h8M8 17h5"/></svg>
            Time Frame
        </a>
        <a href="{{ route('admin.logs.index') }}" class="inline-flex items-center gap-1.5 bg-brand-600 hover:bg-brand-700 text-white text-sm font-medium px-3.5 py-2 rounded-lg transition">Open Logbook</a>
    </div>
</div>

<div class="bg-white border border-gray-200 rounded-xl overflow-x-auto">
    <table class="w-full text-sm">
        <thead>
            <tr class="text-left text-[11px] font-medium uppercase tracking-widest text-gray-400 border-b border-gray-200">
                <th class="px-5 py-3">Date</th>
                <th class="px-4 py-3">AM</th>
                <th class="px-4 py-3">PM</th>
                <th class="px-4 py-3">
                    <a href="{{ route('admin.logs.show', array_filter(['intern' => $intern->id, 'sort' => $sort === 'hours_desc' ? 'hours_asc' : 'hours_desc'])) }}"
                       class="inline-flex items-center gap-1 hover:text-gray-700">
                        Hours
                        @if($sort === 'hours_asc') <span>↑</span>
                        @elseif($sort === 'hours_desc') <span>↓</span>
                        @else <span class="text-gray-300">↕</span>
                        @endif
                    </a>
                </th>
                <th class="px-4 py-3">Notes</th>
                <th class="px-4 py-3">Daily Report</th>
                <th class="px-4 py-3">Capture</th>
                <th class="px-5 py-3 text-right">Actions</th>
            </tr>
        </thead>
        <tbody class="divide-y divide-gray-100">
            @forelse($logs as $log)
                <tr class="hover:bg-gray-50/60 transition-colors">
                    <td class="px-5 py-3 whitespace-nowrap font-medium text-gray-700">{{ $log->date->format('M d, Y') }}</td>
                    <td class="px-4 py-3 text-gray-500 tabular-nums whitespace-nowrap">
                        {{ $log->am_time_in ? \Illuminate\Support\Carbon::parse($log->am_time_in)->format('g:iA') : '—' }}
                        –
                        {{ $log->am_time_out ? \Illuminate\Support\Carbon::parse($log->am_time_out)->format('g:iA') : '—' }}
                    </td>
                    <td class="px-4 py-3 text-gray-500 tabular-nums whitespace-nowrap">
                        {{ $log->pm_time_in ? \Illuminate\Support\Carbon::parse($log->pm_time_in)->format('g:iA') : '—' }}
                        –
                        {{ $log->pm_time_out ? \Illuminate\Support\Carbon::parse($log->pm_time_out)->format('g:iA') : '—' }}
                    </td>
                    <td class="px-4 py-3 whitespace-nowrap">
                        <span class="font-medium text-gray-900 tabular-nums">{{ number_format($log->hours_rendered, 2) }}h</span>
                        @if($log->has_overtime)
                            <span class="block text-[10px] font-medium text-amber-600 tabular-nums">+{{ number_format($log->overtime_hours, 2) }}h OT</span>
                        @endif
                    </td>
                    <td class="px-4 py-3 text-gray-500 max-w-xs truncate">{{ $log->notes ?: '—' }}</td>
                    <td class="px-4 py-3">
                        @if($log->photo_path)
                            @include('partials.report-modal', ['log' => $log, 'intern' => $intern])
                        @elseif($log->photo_required)
                            <span class="inline-flex items-center gap-1.5 text-xs font-medium text-amber-700">
                                <span class="w-1.5 h-1.5 rounded-full bg-amber-500"></span>
                                Awaiting report
                            </span>
                        @else
                            <span class="text-gray-300">—</span>
                        @endif
                    </td>
                    <td class="px-4 py-3 whitespace-nowrap">
                        @include('partials.kiosk-captures', ['log' => $log, 'intern' => $intern])
                        @if(blank($log->kiosk_captures))
                            <span class="text-gray-300">—</span>
                        @endif
                    </td>
                    <td class="px-5 py-3 text-right">
                        <form method="POST" action="{{ route('admin.logs.destroy', $log) }}"
                              data-confirm-title="Delete entry"
                              data-confirm-message="Delete {{ $log->user?->display_name ?? 'this intern' }}'s entry for {{ $log->date->format('M d, Y') }}? This cannot be undone."
                              data-confirm-action="Delete"
                              onsubmit="return askConfirm(this);">
                            @csrf
                            @method('DELETE')
                            <button class="text-red-500 hover:underline text-xs font-medium">Delete</button>
                        </form>
                    </td>
                </tr>
            @empty
                <tr><td colspan="8" class="px-5 py-10 text-center text-gray-400 text-sm">No logged hours yet.</td></tr>
            @endforelse
        </tbody>
    </table>
</div>

<div class="mt-4">
    {{ $logs->appends(['sort' => $sort])->links() }}
</div>

@include('partials.confirm-modal')
@endsection
