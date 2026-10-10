@extends('layouts.app')

@section('title', $intern->full_name)

@section('content')
<div class="flex flex-wrap items-end justify-between gap-3 mb-6">
    <div>
        <a href="{{ route('monitor.dashboard') }}" class="text-xs font-medium text-gray-400 hover:text-gray-700">← My Interns</a>
        <h1 class="text-xl font-semibold tracking-tight text-gray-900 mt-1">{{ $intern->full_name }}</h1>
        <p class="text-sm text-gray-500 mt-0.5">
            {{ $intern->student_id }} · {{ $intern->ojt_track_label }}
            · {{ number_format($intern->accumulated_hours, 1) }} / {{ $intern->target_hours }} hours ({{ $intern->completion_percentage }}%)
        </p>
    </div>
    <div class="flex flex-wrap items-center gap-2">
        <a href="{{ route('monitor.interns.weekly-report', $intern) }}"
           class="inline-flex items-center gap-1.5 bg-white border border-gray-200 hover:bg-gray-50 text-gray-700 text-sm font-medium px-3 py-2 rounded-lg transition">
            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M14 3v5h5"/><path d="M14 3H6a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/></svg>
            Weekly Report
        </a>
        <a href="{{ route('monitor.interns.timesheet', $intern) }}"
           class="inline-flex items-center gap-1.5 bg-white border border-gray-200 hover:bg-gray-50 text-gray-700 text-sm font-medium px-3 py-2 rounded-lg transition">
            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M14 3v5h5"/><path d="M14 3H6a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/></svg>
            Time Frame
        </a>
    </div>
</div>

{{-- Tab bar — client-side switching, deep-linkable via #hash. --}}
<div class="border-b border-gray-200 mb-6" role="tablist">
    <nav class="flex gap-6 -mb-px">
        @foreach(['overview' => 'Overview', 'documents' => 'Documents'] as $tabKey => $tabLabel)
            <button type="button" role="tab" data-tab-button="{{ $tabKey }}"
                onclick="switchInternTab('{{ $tabKey }}')"
                class="pb-3 px-1 border-b-2 text-sm font-medium transition-colors {{ $tabKey === 'overview' ? 'border-brand-600 text-brand-700' : 'border-transparent text-gray-500' }}">
                {{ $tabLabel }}
            </button>
        @endforeach
    </nav>
</div>

<div data-tab-panel="overview">
{{-- Placement --}}
<div class="grid grid-cols-1 sm:grid-cols-2 xl:grid-cols-4 gap-4 mb-8">
    <div class="bg-white border border-gray-200 rounded-xl px-5 py-4 sm:col-span-2">
        <p class="text-[11px] font-medium uppercase tracking-widest text-gray-400 mb-1.5">Office / Company</p>
        @if($intern->office)
            <p class="text-sm font-medium text-gray-900">{{ $intern->office->name }}</p>
            <p class="text-xs text-gray-400 mt-0.5">
                {{ $intern->office->type_label }}@if($intern->office->address) · {{ $intern->office->address }}@endif
            </p>
        @else
            <p class="text-sm text-gray-400">Not placed</p>
        @endif
    </div>
    <div class="bg-white border border-gray-200 rounded-xl px-5 py-4">
        <p class="text-[11px] font-medium uppercase tracking-widest text-gray-400 mb-1.5">Supervisor</p>
        <p class="text-sm text-gray-900">{{ $intern->office?->supervisors->first()?->full_name ?? '—' }}</p>
    </div>
    <div class="bg-white border border-gray-200 rounded-xl px-5 py-4">
        <p class="text-[11px] font-medium uppercase tracking-widest text-gray-400 mb-1.5">Coordinator</p>
        <p class="text-sm text-gray-900">{{ $intern->coordinator?->full_name ?? '—' }}</p>
    </div>
</div>

{{-- Progress + certifications — side by side on wide screens --}}
<div class="grid grid-cols-1 xl:grid-cols-2 gap-4 mb-8">
    <div class="bg-white border border-gray-200 rounded-xl px-5 py-4 flex flex-col justify-center">
        <div class="flex justify-between items-baseline mb-2">
            <span class="text-sm font-medium text-gray-700">Overall Progress</span>
            <span class="text-sm font-semibold tabular-nums {{ $intern->is_complete ? 'text-emerald-600' : 'text-gray-900' }}">
                {{ $intern->completion_percentage }}%
                @if($intern->is_complete) — Completed 🎉 @endif
            </span>
        </div>
        <div class="w-full bg-gray-100 rounded-full h-2">
            <div class="h-2 rounded-full {{ $intern->is_complete ? 'bg-emerald-500' : 'bg-brand-500' }}" style="width: {{ min($intern->completion_percentage, 100) }}%"></div>
        </div>
    </div>

    {{-- Time Frame certifications — the supervisor's per-period sign-off --}}
    <div class="bg-white border border-gray-200 rounded-xl">
        <div class="px-5 py-3.5 border-b border-gray-100 flex flex-wrap items-center justify-between gap-2">
            <h2 class="text-sm font-semibold text-gray-900">Time Frame Certifications</h2>
            @can('certify', $intern)
                <form method="POST" action="{{ route('monitor.interns.certify', $intern) }}" class="flex items-center gap-2">
                    @csrf
                    <input type="month" name="month" value="{{ now()->format('Y-m') }}" max="{{ now()->format('Y-m') }}" required
                        class="px-2 py-1.5 rounded-lg border-gray-200 text-xs text-gray-600 focus:border-brand-400 focus:ring-2 focus:ring-brand-500/15 transition">
                    <button type="submit" class="inline-flex items-center gap-1.5 bg-brand-600 hover:bg-brand-700 text-white text-xs font-medium px-3 py-1.5 rounded-lg transition">
                        <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M20 6 9 17l-5-5"/></svg>
                        Certify period
                    </button>
                </form>
            @endcan
        </div>
        <div class="divide-y divide-gray-100">
            @forelse($certifications as $certification)
                <div class="px-5 py-3 flex items-center justify-between gap-3">
                    <div>
                        <p class="text-sm text-gray-900">{{ $certification->period_label }}</p>
                        <p class="text-xs text-gray-400">by {{ $certification->supervisor?->full_name ?? '—' }} · {{ $certification->certified_at->format('M d, Y') }}</p>
                    </div>
                    <span class="inline-flex items-center px-2 py-0.5 rounded-md text-xs font-medium bg-emerald-50 text-emerald-700">Certified</span>
                </div>
            @empty
                <p class="px-5 py-8 text-center text-sm text-gray-400">
                    @if(auth()->user()->isSupervisor())
                        No periods certified yet — certify a month to sign off {{ $intern->first_name }}'s hours on the timesheet.
                    @else
                        No periods certified yet.
                    @endif
                </p>
            @endforelse
        </div>
    </div>
</div>

{{-- Duty history — supervisors approve/reject entries; coordinators flag --}}
<div class="bg-white border border-gray-200 rounded-xl overflow-x-auto">
    <div class="px-5 py-3.5 border-b border-gray-100 flex flex-wrap items-center justify-between gap-2">
        <h2 class="text-sm font-semibold text-gray-900">Duty History — current set</h2>
        <p class="text-xs text-gray-400">
            @if(auth()->user()->isSupervisor())
                Approve entries you witnessed; reject needs a reason the intern will see.
            @else
                Flag an entry you have a concern about — the office's supervisors take a second look.
            @endif
        </p>
    </div>
    <table class="w-full text-sm">
        <thead>
            <tr class="text-left text-[11px] font-medium uppercase tracking-widest text-gray-400 border-b border-gray-200">
                <th class="px-5 py-3">Date</th>
                <th class="px-4 py-3">AM</th>
                <th class="px-4 py-3">PM</th>
                <th class="px-4 py-3 text-right">Hours</th>
                <th class="px-4 py-3 text-right">OT</th>
                <th class="px-4 py-3">Review</th>
                <th class="px-4 py-3">Capture</th>
                <th class="px-5 py-3 text-right">Actions</th>
            </tr>
        </thead>
        <tbody class="divide-y divide-gray-100">
            @php $t = fn($v) => $v ? \Illuminate\Support\Carbon::parse($v)->format('g:iA') : '—'; @endphp
            @forelse($logs as $log)
                @php
                    $entryLabel = $log->date->format('M d, Y').' — '.$intern->full_name;
                    $canReview = auth()->user()->can('review', $log);
                    $canFlag = auth()->user()->can('flag', $log);
                @endphp
                <tr class="hover:bg-gray-50/60 transition-colors {{ $log->status === 'pending' ? 'bg-amber-50/40' : '' }}">
                    <td class="px-5 py-3 whitespace-nowrap font-medium text-gray-700">{{ $log->date->format('M d, Y') }}</td>
                    <td class="px-4 py-3 text-gray-500 tabular-nums whitespace-nowrap">{{ $t($log->am_time_in) }} – {{ $t($log->am_time_out) }}</td>
                    <td class="px-4 py-3 text-gray-500 tabular-nums whitespace-nowrap">{{ $t($log->pm_time_in) }} – {{ $t($log->pm_time_out) }}</td>
                    <td class="px-4 py-3 text-right font-medium text-gray-900 tabular-nums">{{ number_format($log->hours_rendered, 2) }}h</td>
                    <td class="px-4 py-3 text-right text-amber-600 tabular-nums">{{ $log->has_overtime ? '+'.number_format($log->overtime_hours, 2) : '—' }}</td>
                    <td class="px-4 py-3 whitespace-nowrap">
                        <span class="inline-flex items-center px-2 py-0.5 rounded-md text-xs font-medium {{ $log->review_status['class'] }}">{{ $log->review_status['label'] }}</span>
                        @if($log->status !== 'approved' && $log->review_comment)
                            <span class="block text-[11px] text-gray-400 mt-0.5 max-w-[16rem] truncate" title="By {{ $log->reviewedBy?->full_name ?? '—' }}: {{ $log->review_comment }}">
                                “{{ $log->review_comment }}”
                            </span>
                        @endif
                    </td>
                    <td class="px-5 py-3 text-right whitespace-nowrap">
                        @if($canReview)
                            {{-- Approve is comment-optional and needs no modal. --}}
                            <form method="POST" action="{{ route('monitor.logs.review', $log) }}" class="inline">
                                @csrf
                                <input type="hidden" name="action" value="approve">
                                <button class="inline-flex items-center justify-center w-7 h-7 rounded-md text-gray-400 hover:text-emerald-600 hover:bg-emerald-50 transition" title="Approve entry">
                                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M20 6 9 17l-5-5"/></svg>
                                </button>
                            </form>
                            <button type="button" data-url="{{ route('monitor.logs.review', $log) }}" data-action="reject" data-title="{{ $entryLabel }}"
                                onclick="openReview(this)"
                                class="inline-flex items-center justify-center w-7 h-7 rounded-md text-gray-400 hover:text-red-600 hover:bg-red-50 transition" title="Reject entry (reason required)">
                                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M18 6 6 18M6 6l12 12"/></svg>
                            </button>
                        @elseif($canFlag)
                            <button type="button" data-url="{{ route('monitor.logs.review', $log) }}" data-action="flag" data-title="{{ $entryLabel }}"
                                onclick="openReview(this)"
                                class="inline-flex items-center justify-center w-7 h-7 rounded-md text-gray-400 hover:text-amber-600 hover:bg-amber-50 transition" title="Flag for a second look (reason required)">
                                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M4 15s1-1 4-1 5 2 8 2 4-1 4-1V3s-1 1-4 1-5-2-8-2-4 1-4 1z"/><path d="M4 22v-7"/></svg>
                            </button>
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
                </tr>
            @empty
                <tr><td colspan="8" class="px-5 py-10 text-center text-gray-400 text-sm">No duty days recorded yet.</td></tr>
            @endforelse
        </tbody>
    </table>
</div>

<div class="mt-4">
    {{ $logs->links() }}
</div>

{{-- Shared reject/flag modal — the row buttons fill in the action + target. --}}
<div id="reviewModal" class="hidden fixed inset-0 z-50 flex items-center justify-center bg-gray-900/25 px-4"
     onclick="if(event.target === this) this.classList.add('hidden')">
    <div class="bg-white border border-gray-200 rounded-xl shadow-xl w-full max-w-md">
        <div class="flex items-center justify-between px-6 py-4 border-b border-gray-100">
            <h2 class="font-semibold text-gray-900"><span id="reviewTitle"></span></h2>
            <button type="button" onclick="document.getElementById('reviewModal').classList.add('hidden')" class="text-gray-400 hover:text-gray-900">
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M18 6 6 18M6 6l12 12"/></svg>
            </button>
        </div>
        <form id="reviewForm" method="POST" action="" class="px-6 py-5 space-y-4">
            @csrf
            <input type="hidden" name="action" id="reviewAction">
            <div>
                <label for="reviewComment" class="block text-sm font-medium text-gray-700 mb-1.5">Reason</label>
                <textarea name="comment" id="reviewComment" rows="3"
                    placeholder="Explain the decision — the intern and the admin will see this…"
                    class="w-full px-3 py-2 rounded-lg border-gray-200 text-sm placeholder:text-gray-300 focus:border-brand-400 focus:ring-2 focus:ring-brand-500/15 transition"></textarea>
                <p id="reviewHint" class="text-[11px] text-gray-400 mt-1"></p>
            </div>
            <div class="flex justify-end gap-2">
                <button type="button" onclick="document.getElementById('reviewModal').classList.add('hidden')"
                    class="bg-white border border-gray-200 hover:bg-gray-50 text-gray-700 text-sm font-medium px-3.5 py-2 rounded-lg transition">Cancel</button>
                <button type="submit" id="reviewSubmit" class="bg-brand-600 hover:bg-brand-700 text-white text-sm font-medium px-3.5 py-2 rounded-lg transition">Submit</button>
            </div>
        </form>
    </div>
</div>

@if($errors->any())
    <script>document.getElementById('reviewModal').classList.remove('hidden');</script>
@endif
</div>{{-- /overview panel --}}

<div data-tab-panel="documents" class="hidden">
    @include('partials.intern-documents-panel', ['intern' => $intern, 'submissions' => $submissions])
</div>

@push('scripts')
<script>
// Overview / Documents tabs — client-side, deep-linkable like the admin
// profile's tabs.
const INTERN_TABS = ['overview', 'documents'];

function switchInternTab(key) {
    if (! INTERN_TABS.includes(key)) { key = 'overview'; }

    INTERN_TABS.forEach((name) => {
        const panel = document.querySelector(`[data-tab-panel="${name}"]`);
        const button = document.querySelector(`[data-tab-button="${name}"]`);
        if (! panel || ! button) { return; }

        const active = name === key;
        panel.classList.toggle('hidden', ! active);
        button.classList.toggle('border-brand-600', active);
        button.classList.toggle('text-brand-700', active);
        button.classList.toggle('border-transparent', ! active);
        button.classList.toggle('text-gray-500', ! active);
    });

    history.replaceState(null, '', '#' + key);
}

const internHash = window.location.hash.slice(1);
if (internHash && INTERN_TABS.includes(internHash) && internHash !== 'overview') { switchInternTab(internHash); }

function openReview(button) {
    const form = document.getElementById('reviewForm');
    form.action = button.dataset.url;
    document.getElementById('reviewAction').value = button.dataset.action;
    document.getElementById('reviewTitle').textContent = button.dataset.title;
    const comment = document.getElementById('reviewComment');
    comment.required = true;
    comment.value = '';
    document.getElementById('reviewHint').textContent = 'A reason is required — it explains the decision to the intern and the admin.';
    document.getElementById('reviewSubmit').textContent = button.dataset.action === 'reject' ? 'Reject entry' : 'Flag entry';
    document.getElementById('reviewModal').classList.remove('hidden');
}
</script>
@endpush
@endsection
