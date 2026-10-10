@extends('layouts.app')

@section('title', 'Requirements')

@section('content')
<div>
    <div class="mb-8">
        <h1 class="text-xl font-semibold tracking-tight text-gray-900">OJT Requirements</h1>
        <p class="text-sm text-gray-500 mt-0.5">Download each form filled with your info, complete it, then submit it back — your coordinator reviews every submission here.</p>
    </div>

    {{-- How it works --}}
    <div class="mb-8 flex items-start gap-3 rounded-xl border border-brand-100 bg-brand-50/60 p-4 sm:p-5">
        <svg class="mt-0.5 shrink-0 text-brand-600" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="9"/><path d="M12 16v-4M12 8h.01"/></svg>
        <p class="text-sm text-gray-600 leading-relaxed">
            Download any form below and complete it (by hand or on your computer), then submit the finished copy — PDF, DOC/DOCX, or a clear photo. Approved documents clear the requirement; a rejected one stays listed with your coordinator's remarks so you know what to fix.
        </p>
    </div>

    @php
        // The requirements still lacking an approved submission — the
        // checklist the coordinator tracks on their side too.
        $lacking = $requirements->filter(fn ($r) => ! ($submissions[$r['type']] ?? collect())
            ->contains(fn ($s) => $s->status === 'approved'));
    @endphp

    @if($lacking->isNotEmpty())
        <div class="mb-8 rounded-xl border border-amber-200 bg-amber-50 px-5 py-4">
            <p class="text-sm font-semibold text-amber-900">{{ $lacking->count() }} requirement{{ $lacking->count() === 1 ? '' : 's' }} still lacking</p>
            <p class="text-xs text-amber-700 mt-1">{{ $lacking->pluck('label')->implode(' · ') }}</p>
        </div>
    @else
        <div class="mb-8 rounded-xl border border-emerald-200 bg-emerald-50 px-5 py-4">
            <p class="text-sm font-semibold text-emerald-800">All requirements approved — nothing lacking. 🎉</p>
        </div>
    @endif

    {{-- The school's requirement forms, in official order --}}
    <div class="bg-white border border-gray-200 rounded-xl divide-y divide-gray-100 overflow-hidden">
        @foreach($requirements as $requirement)
            @php
                $mine = $submissions[$requirement['type']] ?? collect();
                $latest = $mine->first();
            @endphp
            <div class="px-5 py-4">
                <div class="flex items-start gap-4">
                    <span class="shrink-0 w-8 h-8 rounded-lg bg-gray-100 text-gray-500 text-sm font-semibold flex items-center justify-center">
                        {{ $loop->iteration }}
                    </span>

                    <div class="min-w-0 flex-1">
                        <div class="flex flex-wrap items-center gap-2">
                            <p class="text-sm font-medium text-gray-900">{{ $requirement['label'] }}</p>
                            @if($latest?->status === 'approved')
                                <span class="inline-flex items-center gap-1 rounded-full bg-emerald-50 border border-emerald-100 px-2 py-0.5 text-[11px] font-medium text-emerald-700">✓ Approved</span>
                            @elseif($latest?->status === 'rejected')
                                <span class="inline-flex items-center gap-1 rounded-full bg-red-50 border border-red-100 px-2 py-0.5 text-[11px] font-medium text-red-700">✕ Rejected</span>
                            @elseif($latest)
                                <span class="inline-flex items-center gap-1 rounded-full bg-amber-50 border border-amber-100 px-2 py-0.5 text-[11px] font-medium text-amber-700">Pending review</span>
                            @else
                                <span class="inline-flex items-center gap-1 rounded-full bg-gray-100 border border-gray-200 px-2 py-0.5 text-[11px] font-medium text-gray-500">Not submitted</span>
                            @endif
                            @if($requirement['autofilled'])
                                <span class="hidden sm:inline-flex items-center gap-1.5 rounded-full bg-brand-50 border border-brand-100 px-2 py-0.5 text-[11px] font-medium text-brand-700">Filled with your info</span>
                            @endif
                        </div>

                        @if($latest?->status === 'rejected' && $latest->remarks)
                            <p class="text-xs text-red-600 mt-1.5"><span class="font-semibold">Rejected:</span> {{ $latest->remarks }}</p>
                        @elseif($latest?->status === 'approved' && $latest->remarks)
                            <p class="text-xs text-emerald-700 mt-1.5"><span class="font-semibold">Remarks:</span> {{ $latest->remarks }}</p>
                        @endif

                        {{-- Submission history --}}
                        @if($mine->isNotEmpty())
                            <div class="mt-2 space-y-1">
                                @foreach($mine as $submission)
                                    <div class="flex flex-wrap items-center gap-x-2 text-[11px] text-gray-400">
                                        <a href="{{ route('intern.requirements.file', $submission) }}" class="text-brand-600 hover:underline truncate max-w-[16rem]">{{ $submission->original_name }}</a>
                                        <span>submitted {{ $submission->created_at->format('M d, Y') }}</span>
                                        @if($submission->status !== 'pending' && $submission->reviewer)
                                            <span>· reviewed by {{ $submission->reviewer->full_name }}</span>
                                        @endif
                                    </div>
                                @endforeach
                            </div>
                        @endif
                    </div>

                    <div class="shrink-0 flex flex-col items-end gap-2">
                        <a href="{{ route('intern.requirements.download', $requirement['type']) }}"
                           class="inline-flex items-center gap-1.5 bg-white border border-gray-200 hover:bg-gray-50 text-gray-700 text-xs font-medium px-3 py-2 rounded-lg transition">
                            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M12 3v12m0 0 4-4m-4 4-4-4"/><path d="M5 21h14"/></svg>
                            Download
                        </a>

                        {{-- Submit the completed form back — an approved or
                             still-pending requirement takes no new file. --}}
                        @unless($latest?->status === 'approved' || $latest?->status === 'pending')
                            <details class="w-full sm:w-64">
                                <summary class="cursor-pointer select-none inline-flex items-center gap-1.5 bg-brand-600 hover:bg-brand-700 text-white text-xs font-medium px-3 py-2 rounded-lg transition list-none">
                                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M12 19V5m0 0-6 6m6-6 6 6"/></svg>
                                    {{ $latest?->status === 'rejected' ? 'Submit again' : 'Submit' }}
                                </summary>
                                <form method="POST" action="{{ route('intern.requirements.submit', $requirement['type']) }}" enctype="multipart/form-data" class="mt-2 space-y-2">
                                    @csrf
                                    <input type="file" name="file" required accept=".pdf,.doc,.docx,.jpg,.jpeg,.png"
                                        class="w-full text-xs text-gray-600 file:mr-3 file:py-1.5 file:px-3 file:rounded-md file:border-0 file:bg-brand-50 file:text-brand-700 file:text-xs file:font-medium hover:file:bg-brand-100">
                                    @error('file') <p class="text-[11px] text-red-600">{{ $message }}</p> @enderror
                                    <button type="submit" class="w-full bg-brand-600 hover:bg-brand-700 text-white text-xs font-medium px-3 py-2 rounded-lg transition">Upload for review</button>
                                </form>
                            </details>
                        @endunless
                    </div>
                </div>
            </div>
        @endforeach
    </div>
</div>
@endsection
