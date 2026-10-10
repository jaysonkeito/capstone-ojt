@extends('layouts.app')

@section('title', 'Requests')

@section('content')
<div class="mb-8">
    <h1 class="text-xl font-semibold tracking-tight text-gray-900">Requests</h1>
    <p class="text-sm text-gray-500 mt-0.5">
        {{ auth()->user()->isCoordinator()
            ? 'Decide your interns\' attendance requests, and follow the requests you\'ve raised with the System Admin.'
            : 'Attendance requests from your office\'s interns, awaiting your decision.' }}
    </p>
</div>

{{-- Attendance requests awaiting a decision --}}
<div class="bg-white border border-gray-200 rounded-xl overflow-hidden mb-8">
    <div class="px-5 py-3.5 border-b border-gray-100 flex items-center justify-between">
        <h2 class="text-sm font-semibold text-gray-900">Attendance requests</h2>
        <span class="text-xs text-gray-400 tabular-nums">{{ $pendingLogRequests->count() }} pending</span>
    </div>
    @forelse($pendingLogRequests as $lr)
        <div class="px-5 py-4 border-b border-gray-100 last:border-0">
            <div class="flex flex-wrap items-start justify-between gap-3">
                <div class="min-w-0">
                    <p class="text-sm text-gray-900">
                        <a href="{{ route('monitor.intern', $lr->intern) }}" class="font-medium hover:text-brand-700">{{ $lr->intern->full_name }}</a>
                        <span class="inline-flex items-center px-2 py-0.5 rounded-md text-[11px] font-medium ml-1 {{ $lr->type === \App\Models\LogRequest::TYPE_CORRECTION ? 'bg-brand-50 text-brand-700' : 'bg-gray-100 text-gray-600' }}">{{ $lr->type_label }}</span>
                        <span class="text-gray-400">· {{ $lr->date->format('M d, Y') }}</span>
                    </p>
                    <p class="text-sm text-gray-500 mt-0.5">“{{ $lr->reason }}”</p>
                    @if($lr->type === \App\Models\LogRequest::TYPE_CORRECTION)
                        <p class="text-xs text-gray-400 mt-1 tabular-nums">
                            Proposed times:
                            @php $t = fn($v) => $v ? \Illuminate\Support\Carbon::parse($v)->format('g:i A') : null; @endphp
                            @foreach(\App\Models\LogRequest::TIME_FIELDS as $field)
                                @if($lr->{$field}) {{ str_replace('_time', ' ', $field) }} {{ $t($lr->{$field}) }} · @endif
                            @endforeach
                            @if($lr->ojtLog) (entry currently: {{ number_format((float) $lr->ojtLog->hours_rendered, 2) }}h) @endif
                        </p>
                    @endif
                </div>
                <div class="flex items-center gap-2 shrink-0">
                    <form method="POST" action="{{ route('monitor.requests.log.decide', $lr) }}">
                        @csrf
                        <input type="hidden" name="action" value="approved">
                        <button class="inline-flex items-center gap-1.5 bg-emerald-600 hover:bg-emerald-700 text-white text-xs font-medium px-3 py-1.5 rounded-lg transition">
                            {{ $lr->type === \App\Models\LogRequest::TYPE_CORRECTION ? 'Approve & apply' : 'Acknowledge' }}
                        </button>
                    </form>
                    <button type="button" data-url="{{ route('monitor.requests.log.decide', $lr) }}"
                        onclick="openReject(this)"
                        class="inline-flex items-center gap-1.5 bg-white border border-gray-200 hover:bg-red-50 hover:text-red-700 text-gray-700 text-xs font-medium px-3 py-1.5 rounded-lg transition">
                        Reject
                    </button>
                </div>
            </div>
        </div>
    @empty
        <p class="px-5 py-10 text-center text-sm text-gray-400">No attendance requests awaiting your decision.</p>
    @endforelse
</div>

{{-- Intern requests: office transfers + consultations awaiting a decision --}}
@if($pendingInternRequests->isNotEmpty())
<div class="bg-white border border-gray-200 rounded-xl overflow-hidden mb-8">
    <div class="px-5 py-3.5 border-b border-gray-100 flex items-center justify-between">
        <h2 class="text-sm font-semibold text-gray-900">Transfer &amp; consultation requests</h2>
        <span class="text-xs text-gray-400 tabular-nums">{{ $pendingInternRequests->count() }} pending</span>
    </div>
    @foreach($pendingInternRequests as $ir)
        <div class="px-5 py-4 border-b border-gray-100 last:border-0">
            <div class="flex flex-wrap items-start justify-between gap-3">
                <div class="min-w-0">
                    <p class="text-sm text-gray-900">
                        <a href="{{ route('monitor.intern', $ir->intern) }}" class="font-medium hover:text-brand-700">{{ $ir->intern->full_name }}</a>
                        <span class="inline-flex items-center px-2 py-0.5 rounded-md text-[11px] font-medium ml-1 {{ $ir->type === 'office_transfer' ? 'bg-brand-50 text-brand-700' : 'bg-gray-100 text-gray-600' }}">{{ $ir->typeLabel() }}</span>
                        @if($ir->mode)
                            <span class="text-gray-400">· {{ \App\Models\InternRequest::MODE_LABELS[$ir->mode] }}</span>
                        @endif
                        @if($ir->office)
                            <span class="text-gray-400">· applying to {{ $ir->office->name }}</span>
                        @endif
                    </p>
                    <p class="text-sm text-gray-500 mt-0.5">“{{ $ir->message }}”</p>
                    <p class="text-[11px] text-gray-400 mt-1">{{ $ir->created_at->format('M d, Y') }}</p>
                </div>
                <div class="flex items-center gap-2 shrink-0">
                    <form method="POST" action="{{ route('monitor.intern-requests.decide', $ir) }}">
                        @csrf
                        <input type="hidden" name="decision" value="approved">
                        <input type="hidden" name="remarks" value="{{ $ir->type === 'office_transfer' ? 'Transfer approved — your placement has been moved.' : 'Consultation approved — see you then.' }}">
                        <button class="inline-flex items-center gap-1.5 bg-emerald-600 hover:bg-emerald-700 text-white text-xs font-medium px-3 py-1.5 rounded-lg transition">Approve</button>
                    </form>
                    <button type="button" data-url="{{ route('monitor.intern-requests.decide', $ir) }}" data-name="{{ $ir->typeLabel() }}"
                        onclick="openInternRequestReject(this)"
                        class="inline-flex items-center gap-1.5 bg-white border border-gray-200 hover:bg-red-50 hover:text-red-700 text-gray-700 text-xs font-medium px-3 py-1.5 rounded-lg transition">
                        Reject
                    </button>
                </div>
            </div>
        </div>
    @endforeach
</div>
@endif

{{-- Coordinator: requirement documents awaiting review --}}
@if($pendingDocuments->isNotEmpty())
<div class="bg-white border border-gray-200 rounded-xl overflow-hidden mb-8">
    <div class="px-5 py-3.5 border-b border-gray-100 flex items-center justify-between">
        <h2 class="text-sm font-semibold text-gray-900">Requirement documents to review</h2>
        <span class="text-xs text-gray-400 tabular-nums">{{ $pendingDocuments->count() }} pending</span>
    </div>
    @foreach($pendingDocuments as $doc)
        <div class="px-5 py-4 border-b border-gray-100 last:border-0 flex flex-wrap items-center justify-between gap-3">
            <div class="min-w-0">
                <p class="text-sm text-gray-900">
                    <a href="{{ route('monitor.intern', $doc->intern) }}#documents" class="font-medium hover:text-brand-700">{{ $doc->intern->full_name }}</a>
                    <span class="text-gray-400">· {{ $doc->typeLabel() }}</span>
                </p>
                <p class="text-xs text-gray-400 mt-0.5">
                    <a href="{{ route('monitor.documents.download', $doc) }}" class="text-brand-600 hover:underline">{{ $doc->original_name }}</a>
                    · submitted {{ $doc->created_at->format('M d, Y') }}
                </p>
            </div>
            <a href="{{ route('monitor.intern', $doc->intern) }}#documents"
                class="inline-flex items-center gap-1.5 bg-brand-600 hover:bg-brand-700 text-white text-xs font-medium px-3 py-1.5 rounded-lg transition shrink-0">
                Review on Documents tab
            </a>
        </div>
    @endforeach
</div>
@endif

{{-- Coordinator: raise requests to the System Admin --}}
@if(auth()->user()->isCoordinator())
    <div class="grid grid-cols-1 lg:grid-cols-2 gap-4 mb-8">
        <div class="bg-white border border-gray-200 rounded-xl p-5">
            <h2 class="text-sm font-semibold text-gray-900 mb-1">Request an intern's placement</h2>
            <p class="text-xs text-gray-400 mb-4">Propose an office for one of your interns — the System Admin approves the assignment.</p>
            <form method="POST" action="{{ route('monitor.requests.placement.store') }}" class="space-y-3">
                @csrf
                <select name="intern_id" required
                    class="w-full px-3 py-2 rounded-lg border-gray-200 text-sm focus:border-brand-400 focus:ring-2 focus:ring-brand-500/15 transition">
                    <option value="">Select intern</option>
                    @foreach($myInterns as $i)
                        <option value="{{ $i->id }}" {{ old('intern_id') == $i->id ? 'selected' : '' }}>{{ $i->full_name }} ({{ $i->student_id }})</option>
                    @endforeach
                </select>
                <select name="office_id" required
                    class="w-full px-3 py-2 rounded-lg border-gray-200 text-sm focus:border-brand-400 focus:ring-2 focus:ring-brand-500/15 transition">
                    <option value="">Select office</option>
                    @foreach($offices as $o)
                        <option value="{{ $o->id }}" {{ old('office_id') == $o->id ? 'selected' : '' }}>{{ $o->name }} ({{ $o->type_label }})</option>
                    @endforeach
                </select>
                @error('office_id') <p class="text-xs text-red-600">{{ $message }}</p> @enderror
                <textarea name="note" rows="2" placeholder="Why this office (optional)…"
                    class="w-full px-3 py-2 rounded-lg border-gray-200 text-sm placeholder:text-gray-300 focus:border-brand-400 focus:ring-2 focus:ring-brand-500/15 transition">{{ old('note') }}</textarea>
                <button type="submit" class="w-full bg-brand-600 hover:bg-brand-700 text-white text-sm font-medium py-2 rounded-lg transition">Submit placement request</button>
            </form>
        </div>

        <div class="bg-white border border-gray-200 rounded-xl p-5">
            <h2 class="text-sm font-semibold text-gray-900 mb-1">Recommend completion of an OJT set</h2>
            <p class="text-xs text-gray-400 mb-4">For interns who have reached their target hours — the System Admin's approval marks the set completed.</p>
            <form method="POST" action="{{ route('monitor.requests.completion.store') }}" class="space-y-3">
                @csrf
                <select name="intern_id" required
                    class="w-full px-3 py-2 rounded-lg border-gray-200 text-sm focus:border-brand-400 focus:ring-2 focus:ring-brand-500/15 transition">
                    <option value="">Select intern</option>
                    @foreach($myInterns as $i)
                        <option value="{{ $i->id }}" {{ old('intern_id') == $i->id ? 'selected' : '' }}>{{ $i->full_name }} ({{ $i->student_id }}) — {{ number_format($i->accumulated_hours, 1) }}/{{ $i->target_hours }}h</option>
                    @endforeach
                </select>
                @error('intern_id') <p class="text-xs text-red-600">{{ $message }}</p> @enderror
                <textarea name="note" rows="2" placeholder="Note for the System Admin (optional)…"
                    class="w-full px-3 py-2 rounded-lg border-gray-200 text-sm placeholder:text-gray-300 focus:border-brand-400 focus:ring-2 focus:ring-brand-500/15 transition">{{ old('note') }}</textarea>
                <button type="submit" class="w-full bg-brand-600 hover:bg-brand-700 text-white text-sm font-medium py-2 rounded-lg transition">Submit completion recommendation</button>
            </form>
        </div>
    </div>

    {{-- My requests to the admin --}}
    <div class="grid grid-cols-1 lg:grid-cols-2 gap-4">
        <div class="bg-white border border-gray-200 rounded-xl">
            <div class="px-5 py-3.5 border-b border-gray-100"><h2 class="text-sm font-semibold text-gray-900">My placement requests</h2></div>
            <div class="divide-y divide-gray-100">
                @forelse($placementRequests as $pr)
                    <div class="px-5 py-3 flex items-center justify-between gap-3">
                        <div class="min-w-0">
                            <p class="text-sm text-gray-900">{{ $pr->intern->full_name }} <span class="text-gray-400">→ {{ $pr->office->name }}</span></p>
                            <p class="text-xs text-gray-400">{{ $pr->created_at->format('M d, Y') }}@if($pr->decidedBy) · decided by {{ $pr->decidedBy->full_name }}@endif @if($pr->decision_comment) · “{{ $pr->decision_comment }}”@endif</p>
                        </div>
                        <span class="shrink-0 inline-flex items-center px-2 py-0.5 rounded-md text-xs font-medium {{ $pr->status_badge['class'] }}">{{ $pr->status_badge['label'] }}</span>
                    </div>
                @empty
                    <p class="px-5 py-8 text-center text-sm text-gray-400">No placement requests yet.</p>
                @endforelse
            </div>
        </div>

        <div class="bg-white border border-gray-200 rounded-xl">
            <div class="px-5 py-3.5 border-b border-gray-100"><h2 class="text-sm font-semibold text-gray-900">My completion recommendations</h2></div>
            <div class="divide-y divide-gray-100">
                @forelse($completionRecommendations as $cr)
                    <div class="px-5 py-3 flex items-center justify-between gap-3">
                        <div class="min-w-0">
                            <p class="text-sm text-gray-900">{{ $cr->intern->full_name }}</p>
                            <p class="text-xs text-gray-400">{{ $cr->created_at->format('M d, Y') }}@if($cr->decidedBy) · decided by {{ $cr->decidedBy->full_name }}@endif @if($cr->decision_comment) · “{{ $cr->decision_comment }}”@endif</p>
                        </div>
                        <span class="shrink-0 inline-flex items-center px-2 py-0.5 rounded-md text-xs font-medium {{ $cr->status_badge['class'] }}">{{ $cr->status_badge['label'] }}</span>
                    </div>
                @empty
                    <p class="px-5 py-8 text-center text-sm text-gray-400">No completion recommendations yet.</p>
                @endforelse
            </div>
        </div>
    </div>
@endif

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
                <textarea name="comment" id="rejectComment" rows="3" required placeholder="The intern will see why their request was rejected…"
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

{{-- Intern-request rejection — same shape as the attendance one, separate
     form because it posts decision/remarks instead of action/comment. --}}
<div id="irRejectModal" class="hidden fixed inset-0 z-50 flex items-center justify-center bg-gray-900/25 px-4"
     onclick="if(event.target === this) this.classList.add('hidden')">
    <div class="bg-white border border-gray-200 rounded-xl shadow-xl w-full max-w-md">
        <div class="flex items-center justify-between px-6 py-4 border-b border-gray-100">
            <h2 class="font-semibold text-gray-900">Reject <span id="irRejectName">request</span></h2>
            <button type="button" onclick="document.getElementById('irRejectModal').classList.add('hidden')" class="text-gray-400 hover:text-gray-900">
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M18 6 6 18M6 6l12 12"/></svg>
            </button>
        </div>
        <form id="irRejectForm" method="POST" action="" class="px-6 py-5 space-y-4">
            @csrf
            <input type="hidden" name="decision" value="rejected">
            <div>
                <label for="irRejectRemarks" class="block text-sm font-medium text-gray-700 mb-1.5">Remarks</label>
                <textarea name="remarks" id="irRejectRemarks" rows="3" required placeholder="The intern will see why their request was rejected…"
                    class="w-full px-3 py-2 rounded-lg border-gray-200 text-sm placeholder:text-gray-300 focus:border-brand-400 focus:ring-2 focus:ring-brand-500/15 transition"></textarea>
            </div>
            <div class="flex justify-end gap-2">
                <button type="button" onclick="document.getElementById('irRejectModal').classList.add('hidden')"
                    class="bg-white border border-gray-200 hover:bg-gray-50 text-gray-700 text-sm font-medium px-3.5 py-2 rounded-lg transition">Cancel</button>
                <button type="submit" class="bg-red-600 hover:bg-red-700 text-white text-sm font-medium px-3.5 py-2 rounded-lg transition">Reject request</button>
            </div>
        </form>
    </div>
</div>

@push('scripts')
<script>
function openReject(button) {
    const form = document.getElementById('rejectForm');
    form.action = button.dataset.url;
    document.getElementById('rejectComment').value = '';
    document.getElementById('rejectModal').classList.remove('hidden');
}

function openInternRequestReject(button) {
    document.getElementById('irRejectForm').action = button.dataset.url;
    document.getElementById('irRejectName').textContent = button.dataset.name.toLowerCase() + ' request';
    document.getElementById('irRejectRemarks').value = '';
    document.getElementById('irRejectModal').classList.remove('hidden');
}
</script>
@endpush
@endsection
