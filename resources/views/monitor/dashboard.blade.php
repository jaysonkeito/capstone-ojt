@extends('layouts.app')

@section('title', 'My Interns')

@section('content')
<div class="flex flex-wrap items-end justify-between gap-3 mb-8">
    <div>
        <h1 class="text-xl font-semibold tracking-tight text-gray-900">
            {{ $role === 'coordinator' ? 'My Interns' : ($office?->name ?? 'My Office') . ' Interns' }}
        </h1>
        <p class="text-sm text-gray-500 mt-0.5">
            {{ $role === 'coordinator'
                ? 'Every intern assigned to you, with their office and supervisor.'
                : 'Every intern placed at '.($office?->name ?? 'your office').', with rendered hours and their coordinator.' }}
        </p>
    </div>
    <div class="flex flex-wrap items-center gap-2">
        @if($role === 'supervisor')
            <a href="{{ route('monitor.logs.create') }}"
               class="inline-flex items-center gap-1.5 bg-brand-600 hover:bg-brand-700 text-white text-sm font-medium px-3.5 py-2 rounded-lg transition">
                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 5v14M5 12h14"/></svg>
                Add Missing Entry
            </a>
        @endif
        <form method="GET" action="{{ route('monitor.export') }}" class="flex items-center gap-1.5">
            <input type="date" name="from" value="{{ request('from', today()->startOfMonth()->format('Y-m-d')) }}" title="From"
                class="px-2 py-1.5 rounded-lg border-gray-200 text-xs text-gray-600 focus:border-brand-400 focus:ring-2 focus:ring-brand-500/15 transition">
            <span class="text-gray-300 text-xs">→</span>
            <input type="date" name="to" value="{{ request('to', today()->format('Y-m-d')) }}" title="To"
                class="px-2 py-1.5 rounded-lg border-gray-200 text-xs text-gray-600 focus:border-brand-400 focus:ring-2 focus:ring-brand-500/15 transition">
            <button type="submit" class="inline-flex items-center gap-1.5 bg-white border border-gray-200 hover:bg-gray-50 text-gray-700 text-xs font-medium px-2.5 py-1.5 rounded-lg transition">
                <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><path d="M7 10l5 5 5-5"/><path d="M12 15V3"/></svg>
                Export CSV
            </button>
        </form>
    </div>
</div>

{{-- Stats --}}
<div class="grid grid-cols-2 lg:grid-cols-5 divide-x divide-gray-200 border-y border-gray-200 mb-8">
    <div class="py-5 pr-4">
        <p class="text-[11px] font-medium uppercase tracking-widest text-gray-400">Interns</p>
        <p class="text-3xl font-semibold tracking-tight text-gray-900 mt-1.5 tabular-nums">{{ $stats['interns'] }}</p>
    </div>
    <div class="py-5 px-4">
        <p class="text-[11px] font-medium uppercase tracking-widest text-gray-400">Active</p>
        <p class="text-3xl font-semibold tracking-tight text-emerald-600 mt-1.5 tabular-nums">{{ $stats['active'] }}</p>
    </div>
    <div class="py-5 px-4">
        <p class="text-[11px] font-medium uppercase tracking-widest text-gray-400">Completed OJT</p>
        <p class="text-3xl font-semibold tracking-tight text-gray-900 mt-1.5 tabular-nums">{{ $stats['completed'] }}</p>
    </div>
    <div class="py-5 px-4">
        <p class="text-[11px] font-medium uppercase tracking-widest text-gray-400">Hours Rendered</p>
        <p class="text-3xl font-semibold tracking-tight text-gray-900 mt-1.5 tabular-nums">{{ number_format($stats['hours'], 1) }}</p>
    </div>
    <div class="py-5 pl-4">
        <p class="text-[11px] font-medium uppercase tracking-widest text-gray-400">Pending Review</p>
        <p class="text-3xl font-semibold tracking-tight {{ $stats['pendingReview'] ? 'text-amber-600' : 'text-gray-900' }} mt-1.5 tabular-nums">{{ $stats['pendingReview'] }}</p>
    </div>
</div>

{{-- Today board — live duty state + rankings --}}
<div class="mb-8">
    <div class="flex items-center justify-between mb-4">
        <h2 class="text-sm font-semibold text-gray-900">Today</h2>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-4">
        {{-- On duty now --}}
        <div class="bg-white border border-gray-200 rounded-xl">
            <div class="px-5 py-3 border-b border-gray-100 flex items-center justify-between">
                <h3 class="text-xs font-medium uppercase tracking-widest text-gray-400">On duty now</h3>
                <span class="text-xs text-gray-400 tabular-nums">{{ $onDuty->count() }}</span>
            </div>
            <div class="divide-y divide-gray-100 max-h-64 overflow-y-auto">
                @forelse($onDuty as $log)
                    <div class="px-5 py-2.5 flex items-center justify-between text-sm">
                        <a href="{{ route('monitor.intern', $log->user) }}" class="text-gray-900 hover:text-brand-700">{{ $log->user->full_name }}</a>
                        <span class="text-xs text-gray-400 tabular-nums">in {{ \Illuminate\Support\Carbon::parse($log->am_time_in)->format('g:i A') }}</span>
                    </div>
                @empty
                    <p class="px-5 py-6 text-center text-sm text-gray-400">Nobody is on duty right now.</p>
                @endforelse
            </div>
        </div>

        {{-- Top 3 highest hours --}}
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
                            <a href="{{ route('monitor.intern', $intern) }}" class="text-gray-900 hover:text-brand-700">{{ $intern->full_name }}</a>
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

        {{-- Top 3 least hours --}}
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
                            <a href="{{ route('monitor.intern', $intern) }}" class="text-gray-900 hover:text-brand-700">{{ $intern->full_name }}</a>
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
    </div>
</div>

{{-- Sortable headers — every header sorts by progress (hours). --}}
@php
    $nextDir = $dir === 'asc' ? 'desc' : 'asc';
    $progressArrow = $dir === 'asc' ? '↑' : '↓';
    $progressQuery = ['dir' => $nextDir];
    $sortUrl = route('monitor.dashboard', $progressQuery);
@endphp

{{-- Intern table --}}
<div class="bg-white border border-gray-200 rounded-xl overflow-x-auto">
    <table class="w-full text-sm">
        <thead>
            <tr class="text-left text-[11px] font-medium uppercase tracking-widest text-gray-400 border-b border-gray-200">
                <th class="px-5 py-3"><a href="{{ $sortUrl }}" class="inline-flex items-center gap-1 text-gray-900 hover:text-gray-700 transition">Name <span>{{ $progressArrow }}</span></a></th>
                <th class="px-4 py-3"><a href="{{ $sortUrl }}" class="inline-flex items-center gap-1 text-gray-900 hover:text-gray-700 transition">Student ID <span>{{ $progressArrow }}</span></a></th>
                @if($role === 'coordinator')
                    <th class="px-4 py-3"><a href="{{ $sortUrl }}" class="inline-flex items-center gap-1 text-gray-900 hover:text-gray-700 transition">Office <span>{{ $progressArrow }}</span></a></th>
                    <th class="px-4 py-3">Supervisor</th>
                @else
                    <th class="px-4 py-3"><a href="{{ $sortUrl }}" class="inline-flex items-center gap-1 text-gray-900 hover:text-gray-700 transition">Coordinator <span>{{ $progressArrow }}</span></a></th>
                @endif
                <th class="px-4 py-3"><a href="{{ $sortUrl }}" class="inline-flex items-center gap-1 text-gray-900 hover:text-gray-700 transition">Progress <span>{{ $progressArrow }}</span></a></th>
                <th class="px-5 py-3 text-right"><a href="{{ $sortUrl }}" class="inline-flex items-center gap-1 text-gray-900 hover:text-gray-700 transition">Hours <span>{{ $progressArrow }}</span></a></th>
            </tr>
        </thead>
        <tbody class="divide-y divide-gray-100">
            @forelse($interns as $intern)
                <tr class="hover:bg-gray-50/60 transition-colors">
                    <td class="px-5 py-3">
                        <div class="flex items-center gap-2.5">
                            @include('partials.avatar', ['user' => $intern, 'class' => 'w-8 h-8 text-[10px]'])
                            <a href="{{ route('monitor.intern', $intern) }}" class="font-medium text-gray-900 hover:text-brand-700">{{ $intern->full_name }}</a>
                        </div>
                    </td>
                    <td class="px-4 py-3 text-gray-500 tabular-nums">{{ $intern->student_id }}</td>
                    @if($role === 'coordinator')
                        <td class="px-4 py-3">
                            @if($intern->office)
                                <span class="text-gray-700">{{ $intern->office->name }}</span>
                                <span class="ml-1.5 inline-flex items-center px-1.5 py-0.5 rounded text-[10px] font-medium {{ $intern->office->type === 'external' ? 'bg-amber-50 text-amber-700' : 'bg-gray-100 text-gray-500' }}">{{ $intern->office->type_label }}</span>
                            @else
                                <span class="text-gray-300">Not placed</span>
                            @endif
                        </td>
                        <td class="px-4 py-3 text-gray-500">{{ $intern->office?->supervisors->first()?->full_name ?? '—' }}</td>
                    @else
                        <td class="px-4 py-3 text-gray-500">{{ $intern->coordinator?->full_name ?? '—' }}</td>
                    @endif
                    <td class="px-4 py-3">
                        <div class="flex items-center gap-2">
                            <div class="w-20 h-1.5 bg-gray-100 rounded-full overflow-hidden">
                                <div class="h-full rounded-full {{ $intern->is_complete ? 'bg-emerald-500' : 'bg-brand-500' }}" style="width: {{ min($intern->completion_percentage, 100) }}%"></div>
                            </div>
                            <span class="text-xs text-gray-400 tabular-nums">{{ $intern->completion_percentage }}%</span>
                        </div>
                    </td>
                    <td class="px-5 py-3 text-right text-gray-500 tabular-nums whitespace-nowrap">
                        {{ number_format($intern->accumulated_hours, 1) }} / {{ $intern->target_hours }}h
                    </td>
                </tr>
            @empty
                <tr><td colspan="6" class="px-5 py-10 text-center text-gray-400 text-sm">
                    {{ $role === 'coordinator'
                        ? 'No interns assigned to you yet — the admin assigns interns to coordinators.'
                        : 'No interns placed at this office yet — the admin places interns at offices.' }}
                </td></tr>
            @endforelse
        </tbody>
    </table>
</div>

<div class="mt-4">
    {{ $interns->links() }}
</div>
@endsection
