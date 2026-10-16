{{-- The six live boards as vertical cards, three across on wide screens
     (two rows), stacked on narrow ones: On Duty Now, Late Today, Top 5
     Highest Hours, Top 5 Least Hours, Near Completion, Completed OJT.
     Shared by the staff dashboard (admin/coordinator/supervisor) and the
     monitor dashboard (dean/chairs); the caller supplies $internRoute /
     $logRoute since the deep links differ. --}}

<div class="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-3 gap-4">

    {{-- On Duty Now — each arrival marked Late or On-time against the
         intern's own office window. --}}
    <div class="bg-white border border-gray-200 rounded-xl">
        <div class="px-5 py-3 border-b border-gray-100 flex items-center justify-between">
            <h3 class="text-xs font-medium uppercase tracking-widest text-gray-400">On duty now</h3>
            <span class="text-xs text-gray-400 tabular-nums">{{ $onDuty->count() }}</span>
        </div>
        <div class="divide-y divide-gray-100 max-h-64 overflow-y-auto">
            @forelse($onDuty as $log)
                <div class="px-5 py-2.5 flex items-center justify-between gap-2 text-sm">
                    <a href="{{ route($logRoute, $log->user) }}" class="text-gray-900 hover:text-brand-700 truncate">{{ $log->user->full_name }}</a>
                    <span class="flex items-center gap-2 shrink-0">
                        <span class="inline-flex items-center px-1.5 py-0.5 rounded text-[10px] font-medium
                            {{ ($log->arrival_status ?? 'on-time') === 'late' ? 'bg-amber-50 text-amber-700' : 'bg-emerald-50 text-emerald-700' }}">
                            {{ ($log->arrival_status ?? 'on-time') === 'late' ? 'Late' : 'On-Time' }}
                        </span>
                        <span class="text-xs text-gray-400 tabular-nums">in {{ \Illuminate\Support\Carbon::parse($log->am_time_in)->format('g:i A') }}</span>
                    </span>
                </div>
            @empty
                <p class="px-5 py-6 text-center text-sm text-gray-400">Nobody is on duty right now.</p>
            @endforelse
        </div>
    </div>

    {{-- Late today — right beside On Duty: everyone whose morning
         arrival landed past their office's grace window. --}}
    <div class="bg-white border border-gray-200 rounded-xl">
        <div class="px-5 py-3 border-b border-gray-100 flex items-center justify-between">
            <h3 class="text-xs font-medium uppercase tracking-widest text-gray-400">Late today</h3>
            <span class="text-xs text-gray-400 tabular-nums">{{ $lateToday->count() }}</span>
        </div>
        <div class="divide-y divide-gray-100 max-h-64 overflow-y-auto">
            @forelse($lateToday as $log)
                <div class="px-5 py-2.5 flex items-center justify-between gap-2 text-sm">
                    <a href="{{ route($logRoute, $log->user) }}" class="text-gray-900 hover:text-brand-700 truncate">{{ $log->user->full_name }}</a>
                    <span class="flex items-center gap-2 shrink-0">
                        <span class="inline-flex items-center px-1.5 py-0.5 rounded text-[10px] font-medium bg-amber-50 text-amber-700 shrink-0">Late</span>
                        <span class="text-xs text-gray-400 tabular-nums">in {{ \Illuminate\Support\Carbon::parse($log->am_time_in)->format('g:i A') }}</span>
                    </span>
                </div>
            @empty
                <p class="px-5 py-6 text-center text-sm text-gray-400">No late arrivals today.</p>
            @endforelse
        </div>
    </div>

    {{-- Top 5 Highest Hours --}}
    <div class="bg-white border border-gray-200 rounded-xl">
        <div class="px-5 py-3 border-b border-gray-100 flex items-center justify-between">
            <h3 class="text-xs font-medium uppercase tracking-widest text-gray-400">Top 5 highest hours</h3>
            <span class="w-1.5 h-1.5 rounded-full bg-emerald-500"></span>
        </div>
        <div class="divide-y divide-gray-100 max-h-64 overflow-y-auto">
            @forelse($topHighest as $intern)
                <div class="px-5 py-2.5 flex items-center justify-between gap-2 text-sm">
                    <span class="flex items-center gap-2.5 min-w-0">
                        <span class="text-xs font-semibold text-emerald-600 tabular-nums w-4">#{{ $loop->iteration }}</span>
                        <a href="{{ route($internRoute, $intern) }}" class="text-gray-900 hover:text-brand-700 truncate">{{ $intern->full_name }}</a>
                    </span>
                    <span class="flex items-center gap-2 shrink-0">
                        <span class="text-xs font-medium text-emerald-600 tabular-nums">{{ number_format($intern->accumulated_hours, 1) }}h</span>
                        <span class="text-[10px] text-gray-400 tabular-nums">{{ $intern->completion_percentage }}%</span>
                    </span>
                </div>
            @empty
                <p class="px-5 py-6 text-center text-sm text-gray-400">No interns with hours yet.</p>
            @endforelse
        </div>
    </div>

    {{-- Top 5 Least Hours --}}
    <div class="bg-white border border-gray-200 rounded-xl">
        <div class="px-5 py-3 border-b border-gray-100 flex items-center justify-between">
            <h3 class="text-xs font-medium uppercase tracking-widest text-gray-400">Top 5 least hours</h3>
            <span class="w-1.5 h-1.5 rounded-full bg-amber-500"></span>
        </div>
        <div class="divide-y divide-gray-100 max-h-64 overflow-y-auto">
            @forelse($topLeast as $intern)
                <div class="px-5 py-2.5 flex items-center justify-between gap-2 text-sm">
                    <span class="flex items-center gap-2.5 min-w-0">
                        <span class="text-xs font-semibold text-amber-600 tabular-nums w-4">#{{ $loop->iteration }}</span>
                        <a href="{{ route($internRoute, $intern) }}" class="text-gray-900 hover:text-brand-700 truncate">{{ $intern->full_name }}</a>
                    </span>
                    <span class="flex items-center gap-2 shrink-0">
                        <span class="text-xs font-medium text-amber-600 tabular-nums">{{ number_format($intern->accumulated_hours, 1) }}h</span>
                        <span class="text-[10px] text-gray-400 tabular-nums">{{ $intern->completion_percentage }}%</span>
                    </span>
                </div>
            @empty
                <p class="px-5 py-6 text-center text-sm text-gray-400">No interns with hours yet.</p>
            @endforelse
        </div>
    </div>

    {{-- Near Completion --}}
    <div class="bg-white border border-gray-200 rounded-xl">
        <div class="px-5 py-3 border-b border-gray-100 flex items-center justify-between">
            <h3 class="text-xs font-medium uppercase tracking-widest text-gray-400">Near completion</h3>
            <span class="w-1.5 h-1.5 rounded-full bg-brand-500"></span>
        </div>
        <div class="divide-y divide-gray-100 max-h-64 overflow-y-auto">
            @forelse($nearCompletion as $intern)
                <div class="px-5 py-2.5 flex items-center justify-between gap-2 text-sm">
                    <a href="{{ route($internRoute, $intern) }}" class="text-gray-900 hover:text-brand-700 truncate">{{ $intern->full_name }}</a>
                    <span class="flex items-center gap-2.5 shrink-0">
                        <span class="w-16 h-1.5 bg-gray-100 rounded-full overflow-hidden">
                            <span class="block h-full rounded-full bg-brand-500" style="width: {{ min($intern->completion_percentage, 100) }}%"></span>
                        </span>
                        <span class="text-xs text-gray-400 tabular-nums">{{ $intern->completion_percentage }}%</span>
                    </span>
                </div>
            @empty
                <p class="px-5 py-6 text-center text-sm text-gray-400">No interns near completion yet.</p>
            @endforelse
        </div>
    </div>

    {{-- Completed OJT --}}
    <div class="bg-white border border-gray-200 rounded-xl">
        <div class="px-5 py-3 border-b border-gray-100 flex items-center justify-between">
            <h3 class="text-xs font-medium uppercase tracking-widest text-gray-400">Completed OJT</h3>
            <span class="text-xs text-gray-400 tabular-nums">{{ $completedList->count() }}</span>
        </div>
        <div class="divide-y divide-gray-100 max-h-64 overflow-y-auto">
            @forelse($completedList as $intern)
                <div class="px-5 py-2.5 flex items-center justify-between gap-2 text-sm">
                    <span class="flex items-center gap-2.5 min-w-0">
                        @include('partials.avatar', ['user' => $intern, 'class' => 'w-7 h-7 text-[9px]'])
                        <a href="{{ route($internRoute, $intern) }}" class="text-gray-900 hover:text-brand-700 truncate">{{ $intern->full_name }}</a>
                    </span>
                    <span class="flex items-center gap-1.5 shrink-0">
                        <span class="text-xs font-medium text-emerald-600 tabular-nums">{{ number_format($intern->accumulated_hours, 1) }}h</span>
                        <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" class="text-emerald-500"><path d="M20 6 9 17l-5-5"/></svg>
                    </span>
                </div>
            @empty
                <p class="px-5 py-6 text-center text-sm text-gray-400">No intern has completed their hours yet.</p>
            @endforelse
        </div>
    </div>
</div>
