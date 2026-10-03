@extends('layouts.app')

@section('title', 'Admin Dashboard')

@section('content')
<div class="flex flex-wrap items-center justify-between gap-3 mb-8">
    <div>
        <h1 class="text-xl font-semibold tracking-tight text-gray-900">Dashboard</h1>
        <p class="text-sm text-gray-500 mt-0.5">{{ now()->format('l, F j, Y') }}</p>
    </div>
    <div class="flex flex-wrap gap-2">
        <a href="{{ route('admin.logs.index') }}" class="inline-flex items-center gap-1.5 bg-white border border-gray-200 hover:bg-gray-50 text-gray-700 text-sm font-medium px-3.5 py-2 rounded-lg transition">Logbook</a>
        <a href="{{ route('admin.interns.create') }}" class="inline-flex items-center gap-1.5 bg-brand-600 hover:bg-brand-700 text-white text-sm font-medium px-3.5 py-2 rounded-lg transition">+ Add Intern</a>
    </div>
</div>

{{-- Today board — live duty state --}}
<div class="mb-10">
    <div class="flex items-center justify-between mb-4">
        <h2 class="text-sm font-semibold text-gray-900">Today</h2>
        <a href="{{ route('admin.logs.index') }}" class="text-xs font-medium text-brand-600 hover:underline">Open logbook →</a>
    </div>

    <div class="flex flex-wrap gap-2">
        <span class="inline-flex items-center gap-2 rounded-full bg-white border border-gray-200 pl-3 pr-3.5 py-1.5 text-xs text-gray-600">
            <span class="w-1.5 h-1.5 rounded-full bg-brand-500"></span>
            <strong class="text-gray-900 tabular-nums">{{ $onDuty->count() }}</strong> on duty now
        </span>
        <span class="inline-flex items-center gap-2 rounded-full bg-white border border-gray-200 pl-3 pr-3.5 py-1.5 text-xs text-gray-600">
            <span class="w-1.5 h-1.5 rounded-full bg-emerald-500"></span>
            <strong class="text-gray-900 tabular-nums">{{ $doneToday }}</strong> done for the day
        </span>
        <span class="inline-flex items-center gap-2 rounded-full bg-white border border-gray-200 pl-3 pr-3.5 py-1.5 text-xs text-gray-600 {{ $lateToday->isEmpty() ? 'opacity-50' : '' }}">
            <span class="w-1.5 h-1.5 rounded-full {{ $lateToday->isEmpty() ? 'bg-gray-300' : 'bg-amber-500' }}"></span>
            <strong class="text-gray-900 tabular-nums">{{ $lateToday->count() }}</strong> late this morning
        </span>
        @if($noScanYet !== null)
            <span class="inline-flex items-center gap-2 rounded-full bg-white border border-gray-200 pl-3 pr-3.5 py-1.5 text-xs text-gray-600 {{ $noScanYet === 0 ? 'opacity-50' : '' }}">
                <span class="w-1.5 h-1.5 rounded-full {{ $noScanYet === 0 ? 'bg-gray-300' : 'bg-gray-400' }}"></span>
                <strong class="text-gray-900 tabular-nums">{{ $noScanYet }}</strong> no scan yet
            </span>
        @endif
    </div>

    @if($lateToday->isNotEmpty())
        <p class="text-xs text-gray-400 mt-3">
            Late: {{ $lateToday->map(fn ($l) => $l->user->first_name.' '.substr($l->user->last_name, 0, 1).'.')->implode(', ') }}
        </p>
    @endif

    <div class="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-4 gap-4 mt-4">
        {{-- On duty now --}}
        <div class="bg-white border border-gray-200 rounded-xl">
            <div class="px-5 py-3 border-b border-gray-100 flex items-center justify-between">
                <h3 class="text-xs font-medium uppercase tracking-widest text-gray-400">On duty now</h3>
                <span class="text-xs text-gray-400 tabular-nums">{{ $onDuty->count() }}</span>
            </div>
            <div class="divide-y divide-gray-100 max-h-64 overflow-y-auto">
                @forelse($onDuty as $log)
                    <div class="px-5 py-2.5 flex items-center justify-between text-sm">
                        <a href="{{ route('admin.logs.show', $log->user) }}" class="text-gray-900 hover:text-brand-700">{{ $log->user->full_name }}</a>
                        <span class="text-xs text-gray-400 tabular-nums">in {{ \Illuminate\Support\Carbon::parse($log->am_time_in)->format('g:i A') }}</span>
                    </div>
                @empty
                    <p class="px-5 py-6 text-center text-sm text-gray-400">Nobody is on duty right now.</p>
                @endforelse
            </div>
        </div>

        {{-- Top 3 Highest Hours --}}
        <div class="bg-white border border-gray-200 rounded-xl">
            <div class="px-5 py-3 border-b border-gray-100 flex items-center justify-between">
                <h3 class="text-xs font-medium uppercase tracking-widest text-gray-400">Top 3 highest hours</h3>
                <span class="w-1.5 h-1.5 rounded-full bg-emerald-500"></span>
            </div>
            <div class="divide-y divide-gray-100">
                @forelse($top3Highest as $intern)
                    <div class="px-5 py-2.5 flex items-center justify-between text-sm">
                        <div class="flex items-center gap-2.5">
                            <span class="text-xs font-semibold text-emerald-600 tabular-nums w-4">#{{ $loop->iteration }}</span>
                            <a href="{{ route('admin.interns.show', $intern) }}" class="text-gray-900 hover:text-brand-700">{{ $intern->full_name }}</a>
                        </div>
                        <div class="flex items-center gap-2">
                            <span class="text-xs font-medium text-emerald-600 tabular-nums">{{ number_format($intern->accumulated_hours, 1) }}h</span>
                            <span class="text-[10px] text-gray-400 tabular-nums">{{ $intern->completion_percentage }}%</span>
                        </div>
                    </div>
                @empty
                    <p class="px-5 py-6 text-center text-sm text-gray-400">No active interns yet.</p>
                @endforelse
            </div>
        </div>

        {{-- Top 3 Least Hours --}}
        <div class="bg-white border border-gray-200 rounded-xl">
            <div class="px-5 py-3 border-b border-gray-100 flex items-center justify-between">
                <h3 class="text-xs font-medium uppercase tracking-widest text-gray-400">Top 3 least hours</h3>
                <span class="w-1.5 h-1.5 rounded-full bg-amber-500"></span>
            </div>
            <div class="divide-y divide-gray-100">
                @forelse($top3Least as $intern)
                    <div class="px-5 py-2.5 flex items-center justify-between text-sm">
                        <div class="flex items-center gap-2.5">
                            <span class="text-xs font-semibold text-amber-600 tabular-nums w-4">#{{ $loop->iteration }}</span>
                            <a href="{{ route('admin.interns.show', $intern) }}" class="text-gray-900 hover:text-brand-700">{{ $intern->full_name }}</a>
                        </div>
                        <div class="flex items-center gap-2">
                            <span class="text-xs font-medium text-amber-600 tabular-nums">{{ number_format($intern->accumulated_hours, 1) }}h</span>
                            <span class="text-[10px] text-gray-400 tabular-nums">{{ $intern->completion_percentage }}%</span>
                        </div>
                    </div>
                @empty
                    <p class="px-5 py-6 text-center text-sm text-gray-400">No active interns yet.</p>
                @endforelse
            </div>
        </div>

        {{-- Recent completions / milestones --}}
        <div class="bg-white border border-gray-200 rounded-xl">
            <div class="px-5 py-3 border-b border-gray-100 flex items-center justify-between">
                <h3 class="text-xs font-medium uppercase tracking-widest text-gray-400">Near completion</h3>
                <span class="w-1.5 h-1.5 rounded-full bg-brand-500"></span>
            </div>
            <div class="divide-y divide-gray-100">
                @php
                    $nearCompletion = $internProgress->filter(fn ($i) => ! $i->is_complete && $i->completion_percentage >= 75)->take(5);
                @endphp
                @forelse($nearCompletion as $intern)
                    <div class="px-5 py-2.5 flex items-center justify-between text-sm">
                        <a href="{{ route('admin.interns.show', $intern) }}" class="text-gray-900 hover:text-brand-700">{{ $intern->full_name }}</a>
                        <div class="flex items-center gap-2.5">
                            <div class="w-16 h-1.5 bg-gray-100 rounded-full overflow-hidden">
                                <div class="h-full rounded-full bg-brand-500" style="width: {{ min($intern->completion_percentage, 100) }}%"></div>
                            </div>
                            <span class="text-xs text-gray-400 tabular-nums">{{ $intern->completion_percentage }}%</span>
                        </div>
                    </div>
                @empty
                    <p class="px-5 py-6 text-center text-sm text-gray-400">No interns near completion yet.</p>
                @endforelse
            </div>
        </div>
    </div>
</div>

{{-- Stats — hairline-divided numbers, no boxes --}}
<div class="grid grid-cols-2 lg:grid-cols-4 divide-x divide-gray-200 border-y border-gray-200 mb-10">
    <div class="py-5 pr-4">
        <p class="text-[11px] font-medium uppercase tracking-widest text-gray-400">Total Interns</p>
        <p class="text-3xl font-semibold tracking-tight text-gray-900 mt-1.5">{{ $stats['total_interns'] }}</p>
    </div>
    <div class="py-5 px-4">
        <p class="text-[11px] font-medium uppercase tracking-widest text-gray-400">Active</p>
        <p class="text-3xl font-semibold tracking-tight text-emerald-600 mt-1.5">{{ $stats['active_interns'] }}</p>
    </div>
    <div class="py-5 px-4">
        <p class="text-[11px] font-medium uppercase tracking-widest text-gray-400">Completed OJT</p>
        <p class="text-3xl font-semibold tracking-tight text-gray-900 mt-1.5">{{ $stats['completed_interns'] }}</p>
    </div>
    <div class="py-5 pl-4">
        <p class="text-[11px] font-medium uppercase tracking-widest text-gray-400">Hours Logged</p>
        <p class="text-3xl font-semibold tracking-tight text-gray-900 mt-1.5">{{ number_format($stats['total_hours_logged'], 1) }}</p>
    </div>
</div>

{{-- Additional stats row --}}
<div class="grid grid-cols-2 lg:grid-cols-4 divide-x divide-gray-200 border-b border-gray-200 mb-10">
    <div class="py-5 pr-4">
        <p class="text-[11px] font-medium uppercase tracking-widest text-gray-400">Pending</p>
        <p class="text-3xl font-semibold tracking-tight text-amber-600 mt-1.5">{{ $stats['pending_interns'] }}</p>
    </div>
    <div class="py-5 px-4">
        <p class="text-[11px] font-medium uppercase tracking-widest text-gray-400">Avg Hours</p>
        <p class="text-3xl font-semibold tracking-tight text-gray-900 mt-1.5">{{ $stats['average_hours'] }}<span class="text-lg text-gray-400">h</span></p>
    </div>
    <div class="py-5 px-4">
        <p class="text-[11px] font-medium uppercase tracking-widest text-gray-400">Avg Progress</p>
        <p class="text-3xl font-semibold tracking-tight text-gray-900 mt-1.5">{{ $stats['average_progress'] }}<span class="text-lg text-gray-400">%</span></p>
    </div>
    <div class="py-5 pl-4">
        <p class="text-[11px] font-medium uppercase tracking-widest text-gray-400">Completion Rate</p>
        <p class="text-3xl font-semibold tracking-tight text-gray-900 mt-1.5">
            {{ $stats['total_interns'] > 0 ? round($stats['completed_interns'] / $stats['total_interns'] * 100, 1) : 0 }}<span class="text-lg text-gray-400">%</span>
        </p>
    </div>
</div>

{{-- Intern progress --}}
<div>
    <div class="flex items-center justify-between mb-4">
        <h2 class="text-sm font-semibold text-gray-900">Intern Progress</h2>
        <a href="{{ route('admin.interns.index') }}" class="text-xs font-medium text-brand-600 hover:underline">View all →</a>
    </div>

    <div class="bg-white border border-gray-200 rounded-xl overflow-hidden">
        <table class="w-full text-sm">
            <thead>
                <tr class="text-left text-[11px] font-medium uppercase tracking-widest text-gray-400 border-b border-gray-200">
                    <th class="px-5 py-3">Intern</th>
                    <th class="px-4 py-3">Student ID</th>
                    <th class="px-4 py-3">Progress</th>
                    <th class="px-5 py-3 text-right">Hours</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-100">
                @forelse($internProgress->take(15) as $i)
                    <tr class="hover:bg-gray-50/60 transition-colors">
                        <td class="px-5 py-3 text-gray-900">{{ $i->full_name }}</td>
                        <td class="px-4 py-3 text-gray-500">{{ $i->student_id }}</td>
                        <td class="px-4 py-3">
                            <div class="flex items-center gap-2.5">
                                <div class="w-28 h-1.5 bg-gray-100 rounded-full overflow-hidden">
                                    <div class="h-full rounded-full {{ $i->is_complete ? 'bg-emerald-500' : 'bg-brand-500' }}"
                                         style="width: {{ min($i->completion_percentage, 100) }}%"></div>
                                </div>
                                <span class="text-xs text-gray-400 tabular-nums">{{ $i->completion_percentage }}%</span>
                            </div>
                        </td>
                        <td class="px-5 py-3 text-right text-gray-500 tabular-nums whitespace-nowrap">
                            {{ number_format($i->accumulated_hours, 1) }} / {{ $i->target_hours }}h
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="4" class="px-5 py-10 text-center text-gray-400 text-sm">No interns yet — add your first one.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>
@endsection
