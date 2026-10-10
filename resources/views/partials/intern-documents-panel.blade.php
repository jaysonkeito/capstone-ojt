{{-- The intern's requirement submissions, as the reviewer sees them: what
     has been submitted, what is approved, what is still lacking — and, for
     the intern's coordinator (or the System Admin), the approve/reject
     decision with the remarks the intern will read.

     Expects: $intern (User), $submissions (SubmittedDocument collection,
     all statuses, newest first — grouped in the view by type). --}}
@php
    // requirementTypes() returns a plain array — wrap it so the summary
    // math reads like the rest of the panel.
    $requirementTypes = collect(\App\Models\DocumentTemplate::requirementTypes());
    $canReview = auth()->user()->isAdmin()
        || (auth()->user()->isCoordinator() && auth()->user()->monitors($intern));
    $grouped = $submissions->groupBy('type');
    $approvedCount = $requirementTypes->filter(fn ($meta, $type) => ($grouped[$type] ?? collect())
        ->contains(fn ($s) => $s->status === 'approved'))->count();
@endphp

<div class="space-y-5">
    <div class="rounded-xl border px-5 py-4 {{ $approvedCount === $requirementTypes->count() ? 'border-emerald-200 bg-emerald-50' : 'border-amber-200 bg-amber-50' }}">
        <p class="text-sm font-semibold {{ $approvedCount === $requirementTypes->count() ? 'text-emerald-800' : 'text-amber-900' }}">
            {{ $approvedCount }} of {{ $requirementTypes->count() }} requirements approved
        </p>
        <p class="text-xs {{ $approvedCount === $requirementTypes->count() ? 'text-emerald-700' : 'text-amber-700' }} mt-0.5">
            @if($approvedCount === $requirementTypes->count())
                The complete requirement set is on file for {{ $intern->first_name }}.
            @else
                Still lacking: {{ $requirementTypes
                    ->filter(fn ($meta, $type) => ! ($grouped[$type] ?? collect())->contains(fn ($s) => $s->status === 'approved'))
                    ->map(fn ($meta) => $meta['label'])
                    ->values()
                    ->implode(' · ') }}
            @endif
        </p>
    </div>

    <div class="bg-white border border-gray-200 rounded-xl divide-y divide-gray-100 overflow-hidden">
        @foreach($requirementTypes as $type => $meta)
            @php
                $forType = $grouped[$type] ?? collect();
                $latest = $forType->first();
            @endphp
            <div class="px-5 py-4">
                <div class="flex flex-wrap items-start justify-between gap-3">
                    <div class="min-w-0 flex-1">
                        <div class="flex flex-wrap items-center gap-2">
                            <p class="text-sm font-medium text-gray-900">{{ $meta['label'] }}</p>
                            @if($latest?->status === 'approved')
                                <span class="inline-flex items-center rounded-full bg-emerald-50 border border-emerald-100 px-2 py-0.5 text-[11px] font-medium text-emerald-700">✓ Approved</span>
                            @elseif($latest?->status === 'rejected')
                                <span class="inline-flex items-center rounded-full bg-red-50 border border-red-100 px-2 py-0.5 text-[11px] font-medium text-red-700">✕ Rejected</span>
                            @elseif($latest)
                                <span class="inline-flex items-center rounded-full bg-amber-50 border border-amber-100 px-2 py-0.5 text-[11px] font-medium text-amber-700">Pending review</span>
                            @else
                                <span class="inline-flex items-center rounded-full bg-gray-100 border border-gray-200 px-2 py-0.5 text-[11px] font-medium text-gray-500">Nothing submitted</span>
                            @endif
                        </div>

                        @if($latest?->remarks)
                            <p class="text-xs mt-1.5 {{ $latest->status === 'rejected' ? 'text-red-600' : 'text-emerald-700' }}">
                                <span class="font-semibold">{{ $latest->status === 'rejected' ? 'Rejected' : 'Remarks' }}:</span> {{ $latest->remarks }}
                            </p>
                        @endif

                        @if($forType->isNotEmpty())
                            <div class="mt-2 space-y-1">
                                @foreach($forType as $submission)
                                    <div class="flex flex-wrap items-center gap-x-2 text-[11px] text-gray-400">
                                        <a href="{{ route('monitor.documents.download', $submission) }}" class="text-brand-600 hover:underline truncate max-w-[16rem]">{{ $submission->original_name }}</a>
                                        <span>submitted {{ $submission->created_at->format('M d, Y') }}</span>
                                        @if($submission->status !== 'pending' && $submission->reviewer)
                                            <span>· reviewed by {{ $submission->reviewer->full_name }}</span>
                                        @endif
                                    </div>
                                @endforeach
                            </div>
                        @endif
                    </div>

                    @if($canReview && $latest?->status === 'pending')
                        <div class="shrink-0 flex flex-col sm:flex-row gap-2 w-full sm:w-auto">
                            {{-- Approve: clears the requirement from the lacking list. --}}
                            <form method="POST" action="{{ route('monitor.documents.review', $latest) }}"
                                data-confirm-title="Approve document"
                                data-confirm-message="Approve {{ $intern->first_name }}'s {{ $meta['label'] }}? It clears the requirement."
                                data-confirm-action="Approve"
                                onsubmit="return askConfirm(this);"
                                class="flex-1 sm:flex-none">
                                @csrf
                                <input type="hidden" name="decision" value="approved">
                                <input type="hidden" name="remarks" value="Requirements complete.">
                                <button type="submit" class="w-full sm:w-auto bg-emerald-600 hover:bg-emerald-700 text-white text-xs font-medium px-3 py-2 rounded-lg transition">Approve</button>
                            </form>

                            {{-- Reject: stays listed, remarks tell the intern what to fix. --}}
                            <details class="flex-1 sm:flex-none">
                                <summary class="cursor-pointer select-none list-none bg-white border border-red-200 hover:bg-red-50 text-red-600 text-xs font-medium px-3 py-2 rounded-lg transition text-center">Reject…</summary>
                                <form method="POST" action="{{ route('monitor.documents.review', $latest) }}" class="mt-2 space-y-2 sm:w-64">
                                    @csrf
                                    <input type="hidden" name="decision" value="rejected">
                                    <textarea name="remarks" rows="2" required maxlength="1000" placeholder="Why is it rejected? The intern reads this."
                                        class="w-full px-3 py-2 rounded-lg border-gray-200 text-xs focus:border-brand-400 focus:ring-2 focus:ring-brand-500/15 transition"></textarea>
                                    <button type="submit" class="w-full bg-red-600 hover:bg-red-700 text-white text-xs font-medium px-3 py-2 rounded-lg transition">Reject with remarks</button>
                                </form>
                            </details>
                        </div>
                    @endif
                </div>
            </div>
        @endforeach
    </div>
</div>
