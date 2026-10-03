@extends('layouts.app')

@section('title', 'My Time Frame')

@section('content')
<div class="flex flex-wrap items-end justify-between gap-3 mb-8">
    <div>
        <a href="{{ route('intern.dashboard') }}" class="text-xs font-medium text-gray-400 hover:text-gray-700">← My Dashboard</a>
        <h1 class="text-xl font-semibold tracking-tight text-gray-900 mt-1">My Time Frame</h1>
        <p class="text-sm text-gray-500 mt-0.5">
            @if($enrollment)
                {{ $enrollment->label }} · {{ number_format($renderedHours, 2) }} / {{ number_format($targetHours, 2) }} hours ({{ number_format($enrollment->completion_percentage, 1) }}%)
            @else
                No active OJT set yet.
            @endif
        </p>
    </div>
    <div class="flex flex-wrap items-center gap-2">
        <a id="export" href="{{ route('intern.timesheet') }}" title="Download your Time Frame as the admin's Word template (.docx)"
            class="inline-flex items-center gap-1.5 bg-brand-600 hover:bg-brand-700 text-white text-xs font-medium px-2.5 py-1.5 rounded-lg transition">
            <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><path d="M7 10l5 5 5-5"/><path d="M12 15V3"/></svg>
            Export
        </a>
    </div>
</div>

@if($enrollment)
    <div class="grid grid-cols-1 sm:grid-cols-3 gap-4 mb-8">
        <div class="bg-white border border-gray-200 rounded-xl px-5 py-4">
            <p class="text-[11px] font-medium uppercase tracking-widest text-gray-400 mb-1.5">Total Hours Rendered</p>
            <p class="text-lg font-semibold text-gray-900 tabular-nums">{{ number_format($renderedHours, 2) }}</p>
        </div>
        <div class="bg-white border border-gray-200 rounded-xl px-5 py-4">
            <p class="text-[11px] font-medium uppercase tracking-widest text-gray-400 mb-1.5">Required Hours</p>
            <p class="text-lg font-semibold text-gray-900 tabular-nums">{{ number_format($targetHours, 2) }}</p>
        </div>
        <div class="bg-white border border-gray-200 rounded-xl px-5 py-4">
            <p class="text-[11px] font-medium uppercase tracking-widest text-gray-400 mb-1.5">Hours Remaining</p>
            <p class="text-lg font-semibold text-gray-900 tabular-nums">{{ number_format(max($targetHours - $renderedHours, 0), 2) }}</p>
        </div>
    </div>

    <div class="bg-white border border-gray-200 rounded-xl overflow-x-auto">
        <div class="px-5 py-3.5 border-b border-gray-100 flex flex-wrap items-center justify-between gap-2">
            <h2 class="text-sm font-semibold text-gray-900">Duty Days — current set</h2>
            <p class="text-xs text-gray-400">Oldest first, exactly as the exported form reads.</p>
        </div>
        @if($rows !== [])
            <table class="w-full text-sm">
                <thead>
                    <tr class="text-left text-[11px] uppercase tracking-widest text-gray-400 border-b border-gray-100">
                        <th class="px-5 py-2.5 font-medium">Date</th>
                        <th class="px-5 py-2.5 font-medium">Time In / Out</th>
                        <th class="px-5 py-2.5 font-medium text-right">Hours</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    @foreach($rows as $row)
                        <tr class="hover:bg-gray-50/60 transition">
                            <td class="px-5 py-2.5 text-gray-900 tabular-nums whitespace-nowrap">{{ $row['date'] }}</td>
                            <td class="px-5 py-2.5 text-gray-600">{{ $row['time'] }}</td>
                            <td class="px-5 py-2.5 text-gray-900 font-medium tabular-nums text-right">{{ $row['hours'] }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        @else
            <p class="px-5 py-10 text-sm text-gray-400 text-center">No duty days recorded in this OJT set yet — time in at the office scanner to start.</p>
        @endif
        <div class="px-5 py-3.5 border-t border-gray-100 bg-gray-50/60 flex flex-wrap items-center justify-between gap-2">
            <p class="text-xs text-gray-400">Total — {{ $rows === [] ? '0' : count($rows) }} {{ $rows === [] ? 'days' : Str::plural('day', count($rows)) }}</p>
            <p class="text-sm font-semibold text-gray-900 tabular-nums">{{ $totalHours }}</p>
        </div>
    </div>
@else
    <div class="bg-white border border-gray-200 rounded-xl px-6 py-16 text-center">
        <p class="text-sm text-gray-400 mb-1">You're not enrolled in an OJT set yet.</p>
        <p class="text-xs text-gray-400">Once your coordinator starts your set, your duty days and hours will appear here.</p>
    </div>
@endif
@endsection
