@extends('layouts.app')

@section('title', 'Requests')

@section('content')
<div class="flex flex-wrap items-end justify-between gap-3 mb-8">
    <div>
        <h1 class="text-xl font-semibold tracking-tight text-gray-900">Requests</h1>
        <p class="text-sm text-gray-500 mt-0.5">What your coordinators have raised — approve an intern's placement or completion, or reject with a reason.</p>
    </div>
    <form method="GET" action="{{ route('admin.requests.index') }}">
        <select name="status" onchange="this.form.submit()" title="Filter by status"
            class="px-2.5 py-2 rounded-lg border-gray-200 text-xs text-gray-600 focus:border-brand-400 focus:ring-2 focus:ring-brand-500/15 transition">
            <option value="" {{ $status === null ? 'selected' : '' }}>All ({{ $pendingCount }} pending)</option>
            <option value="pending" {{ $status === 'pending' ? 'selected' : '' }}>Pending</option>
            <option value="approved" {{ $status === 'approved' ? 'selected' : '' }}>Approved</option>
            <option value="rejected" {{ $status === 'rejected' ? 'selected' : '' }}>Rejected</option>
        </select>
    </form>
</div>

{{-- Placement requests --}}
<div class="bg-white border border-gray-200 rounded-xl mb-8">
    <div class="px-5 py-3.5 border-b border-gray-100 flex items-center justify-between">
        <h2 class="text-sm font-semibold text-gray-900">Placement requests</h2>
        <span class="text-xs text-gray-400">approval assigns the office</span>
    </div>
    <div class="divide-y divide-gray-100">
        @forelse($placementRequests as $pr)
            <div class="px-5 py-4 flex flex-wrap items-start justify-between gap-3">
                <div class="min-w-0">
                    <p class="text-sm text-gray-900">
                        <a href="{{ route('admin.interns.show', $pr->intern) }}" class="font-medium hover:text-brand-700">{{ $pr->intern->full_name }}</a>
                        <span class="text-gray-400">→</span> {{ $pr->office->name }}
                        <span class="text-xs text-gray-400">({{ $pr->office->type_label }})</span>
                    </p>
                    <p class="text-xs text-gray-400 mt-0.5">
                        by {{ $pr->coordinator->full_name }} · {{ $pr->created_at->format('M d, Y') }}
                        @if($pr->note) · “{{ $pr->note }}” @endif
                        @if($pr->decidedBy) · decided by {{ $pr->decidedBy->full_name }} @if($pr->decision_comment) — “{{ $pr->decision_comment }}” @endif @endif
                    </p>
                    <p class="text-xs text-gray-400 mt-0.5">Currently: {{ $pr->intern->office?->name ?? 'not placed' }} · coordinator {{ $pr->intern->coordinator?->full_name ?? '—' }}</p>
                </div>
                @if($pr->status === 'pending')
                    <div class="flex items-center gap-2 shrink-0">
                        <form method="POST" action="{{ route('admin.requests.placement.decide', $pr) }}">
                            @csrf
                            <input type="hidden" name="action" value="approved">
                            <button class="inline-flex items-center gap-1.5 bg-emerald-600 hover:bg-emerald-700 text-white text-xs font-medium px-3 py-1.5 rounded-lg transition">Approve</button>
                        </form>
                        <button type="button" data-url="{{ route('admin.requests.placement.decide', $pr) }}" onclick="openReject(this)"
                            class="inline-flex items-center gap-1.5 bg-white border border-gray-200 hover:bg-red-50 hover:text-red-700 text-gray-700 text-xs font-medium px-3 py-1.5 rounded-lg transition">Reject</button>
                    </div>
                @else
                    <span class="shrink-0 inline-flex items-center px-2 py-0.5 rounded-md text-xs font-medium {{ $pr->status_badge['class'] }}">{{ $pr->status_badge['label'] }}</span>
                @endif
            </div>
        @empty
            <p class="px-5 py-10 text-center text-sm text-gray-400">No placement requests{{ $status ? " with this status" : '' }}.</p>
        @endforelse
    </div>
    @if($placementRequests->hasPages())
        <div class="px-5 py-3 border-t border-gray-100">{{ $placementRequests->links() }}</div>
    @endif
</div>

{{-- Completion recommendations --}}
<div class="bg-white border border-gray-200 rounded-xl">
    <div class="px-5 py-3.5 border-b border-gray-100 flex items-center justify-between">
        <h2 class="text-sm font-semibold text-gray-900">Completion recommendations</h2>
        <span class="text-xs text-gray-400">approval closes the OJT set</span>
    </div>
    <div class="divide-y divide-gray-100">
        @forelse($completionRecommendations as $cr)
            <div class="px-5 py-4 flex flex-wrap items-start justify-between gap-3">
                <div class="min-w-0">
                    <p class="text-sm text-gray-900">
                        <a href="{{ route('admin.interns.show', $cr->intern) }}" class="font-medium hover:text-brand-700">{{ $cr->intern->full_name }}</a>
                        <span class="text-xs text-gray-400">· {{ number_format($cr->intern->accumulated_hours, 1) }} / {{ $cr->intern->target_hours }} hours</span>
                    </p>
                    <p class="text-xs text-gray-400 mt-0.5">
                        by {{ $cr->coordinator->full_name }} · {{ $cr->created_at->format('M d, Y') }}
                        @if($cr->note) · “{{ $cr->note }}” @endif
                        @if($cr->decidedBy) · decided by {{ $cr->decidedBy->full_name }} @if($cr->decision_comment) — “{{ $cr->decision_comment }}” @endif @endif
                    </p>
                </div>
                @if($cr->status === 'pending')
                    <div class="flex items-center gap-2 shrink-0">
                        <form method="POST" action="{{ route('admin.requests.completion.decide', $cr) }}">
                            @csrf
                            <input type="hidden" name="action" value="approved">
                            <button class="inline-flex items-center gap-1.5 bg-emerald-600 hover:bg-emerald-700 text-white text-xs font-medium px-3 py-1.5 rounded-lg transition">Approve</button>
                        </form>
                        <button type="button" data-url="{{ route('admin.requests.completion.decide', $cr) }}" onclick="openReject(this)"
                            class="inline-flex items-center gap-1.5 bg-white border border-gray-200 hover:bg-red-50 hover:text-red-700 text-gray-700 text-xs font-medium px-3 py-1.5 rounded-lg transition">Reject</button>
                    </div>
                @else
                    <span class="shrink-0 inline-flex items-center px-2 py-0.5 rounded-md text-xs font-medium {{ $cr->status_badge['class'] }}">{{ $cr->status_badge['label'] }}</span>
                @endif
            </div>
        @empty
            <p class="px-5 py-10 text-center text-sm text-gray-400">No completion recommendations{{ $status ? " with this status" : '' }}.</p>
        @endforelse
    </div>
    @if($completionRecommendations->hasPages())
        <div class="px-5 py-3 border-t border-gray-100">{{ $completionRecommendations->links() }}</div>
    @endif
</div>

{{-- Attendance requests — the admin is the fallback adjudicator --}}
<div class="bg-white border border-gray-200 rounded-xl overflow-hidden">
    <div class="px-5 py-4 border-b border-gray-100">
        <h2 class="font-semibold text-gray-900">Attendance Requests</h2>
        <p class="text-xs text-gray-500 mt-0.5">Missed-scan corrections and absence reports from interns — normally decided by their supervisor or coordinator; you are the fallback when needed.</p>
    </div>
    @forelse($logRequests as $lr)
        <div class="px-5 py-4 border-b border-gray-100 flex flex-wrap sm:flex-nowrap items-center gap-3">
            <div class="min-w-0 flex-1">
                <p class="text-sm font-medium text-gray-900">{{ $lr->intern->full_name }}
                    <span class="text-[11px] font-normal text-gray-400">· {{ $lr->type_label }} · {{ $lr->date->format('M d, Y') }}</span></p>
                <p class="text-xs text-gray-500 truncate">{{ $lr->reason }}</p>
                <p class="text-xs text-gray-400">{{ $lr->created_at->format('M d, Y') }}@if($lr->decidedBy) · decided by {{ $lr->decidedBy->full_name }}@if($lr->decision_comment) — “{{ $lr->decision_comment }}” @endif @endif</p>
            </div>
            @if($lr->status === 'pending')
                <div class="flex items-center gap-2 shrink-0">
                    <form method="POST" action="{{ route('monitor.requests.log.decide', $lr) }}">
                        @csrf
                        <input type="hidden" name="action" value="approved">
                        <button type="submit" class="inline-flex items-center gap-1.5 bg-brand-600 hover:bg-brand-700 text-white text-xs font-medium px-3 py-1.5 rounded-lg transition">Accept</button>
                    </form>
                    <button type="button" data-url="{{ route('monitor.requests.log.decide', $lr) }}" onclick="openReject(this)"
                        class="inline-flex items-center gap-1.5 bg-white border border-gray-200 hover:bg-red-50 hover:text-red-700 text-gray-700 text-xs font-medium px-3 py-1.5 rounded-lg transition">Reject</button>
                </div>
            @else
                <span class="shrink-0 inline-flex items-center px-2 py-0.5 rounded-md text-xs font-medium {{ $lr->status_badge['class'] }}">{{ $lr->status_badge['label'] }}</span>
            @endif
        </div>
    @empty
        <p class="px-5 py-10 text-center text-sm text-gray-400">No attendance requests{{ $status ? " with this status" : '' }}.</p>
    @endforelse
    @if($logRequests->hasPages())
        <div class="px-5 py-3 border-t border-gray-100">{{ $logRequests->links() }}</div>
    @endif
</div>

{{-- Shared rejection modal --}}
<div id="rejectModal" class="hidden fixed inset-0 z-50 flex items-center justify-center bg-gray-900/25 px-4"
     onclick="if(event.target === this) this.classList.add('hidden')">
    <div class="bg-white border border-gray-200 rounded-xl shadow-xl w-full max-w-md">
        <div class="flex items-center justify-between px-6 py-4 border-b border-gray-100">
            <h2 class="font-semibold text-gray-900">Reject request</h2>
            <button type="button" onclick="document.getElementById('rejectModal').classList.add('hidden')" class="text-gray-400 hover:text-gray-900">
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M18 6 6 18M6 6l12 12"/></svg>
            </button>
        </div>
        <form id="rejectForm" method="POST" action="" class="px-6 py-5 space-y-4">
            @csrf
            <input type="hidden" name="action" value="rejected">
            <div>
                <label for="rejectComment" class="block text-sm font-medium text-gray-700 mb-1.5">Reason</label>
                <textarea name="comment" id="rejectComment" rows="3" required placeholder="The coordinator will see why their request was rejected…"
                    class="w-full px-3 py-2 rounded-lg border-gray-200 text-sm placeholder:text-gray-300 focus:border-brand-400 focus:ring-2 focus:ring-brand-500/15 transition"></textarea>
            </div>
            <div class="flex justify-end gap-2">
                <button type="button" onclick="document.getElementById('rejectModal').classList.add('hidden')"
                    class="bg-white border border-gray-200 hover:bg-gray-50 text-gray-700 text-sm font-medium px-3.5 py-2 rounded-lg transition">Cancel</button>
                <button type="submit" class="bg-red-600 hover:bg-red-700 text-white text-sm font-medium px-3.5 py-2 rounded-lg transition">Reject request</button>
            </div>
        </form>
    </div>
</div>

@if($errors->any())
    <script>document.getElementById('rejectModal').classList.remove('hidden');</script>
@endif

@push('scripts')
<script>
function openReject(button) {
    const form = document.getElementById('rejectForm');
    form.action = button.dataset.url;
    document.getElementById('rejectComment').value = '';
    document.getElementById('rejectModal').classList.remove('hidden');
}
</script>
@endpush
@endsection
