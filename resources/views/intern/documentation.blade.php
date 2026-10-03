@extends('layouts.app')

@section('title', 'My Journal')

@section('content')
<div class="flex flex-wrap items-end justify-between gap-3 mb-8">
    <div>
        <h1 class="text-xl font-semibold tracking-tight text-gray-900">My Journal</h1>
        <p class="text-sm text-gray-500 mt-0.5">Every duty day of your current OJT set — upload your proof photo and journal message, or edit them anytime. Click a photo to view it full size.</p>
    </div>
    <div class="flex flex-wrap items-center gap-2">
        <a href="{{ route('intern.journals.export') }}" title="Export your journals as the school's Word Weekly Progress Report (.docx)"
            class="inline-flex items-center gap-1.5 bg-brand-600 hover:bg-brand-700 text-white text-xs font-medium px-2.5 py-1.5 rounded-lg transition">
            <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><path d="M7 10l5 5 5-5"/><path d="M12 15V3"/></svg>
            Export Journal
        </a>
        @if($archivedJournals->isNotEmpty())
            <button type="button" onclick="toggleArchiveSection()"
                class="inline-flex items-center gap-1.5 bg-white border border-gray-200 hover:bg-gray-50 text-gray-700 text-xs font-medium px-2.5 py-1.5 rounded-lg transition">
                <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="2" y="4" width="20" height="5" rx="1"/><path d="M4 9v9a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V9"/><path d="M10 13h4"/></svg>
                Archive ({{ $archivedJournals->count() }})
            </button>
        @endif
    </div>
</div>

@if($days->isNotEmpty())
    <div class="grid grid-cols-2 md:grid-cols-3 xl:grid-cols-4 2xl:grid-cols-5 gap-3 sm:gap-4">
        @foreach($days as $log)
            @php
                // Only the current set's clocked-out days accept journals —
                // earlier sets are frozen read-only history.
                $editable = $log->ojtEnrollment && $log->ojtEnrollment->id === $intern->currentEnrollment?->id && $log->clocked_out;
            @endphp
            <div class="bg-white border border-gray-200 rounded-xl overflow-hidden flex flex-col">
                {{-- Photo, or the uniform placeholder --}}
                <div class="relative aspect-square bg-gray-100">
                    @if($log->has_journal)
                        <a href="{{ $log->photo_url }}" target="_blank" title="View full photo — {{ $log->date->format('M d, Y') }}">
                            <img src="{{ $log->photo_url }}" alt="Duty photo {{ $log->date->format('M d, Y') }}"
                                 class="w-full h-full object-cover hover:opacity-90 transition">
                        </a>
                    @else
                        <div class="absolute inset-0 flex flex-col items-center justify-center text-gray-300">
                            <svg width="26" height="26" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="3" width="18" height="18" rx="2"/><circle cx="9" cy="9" r="2"/><path d="m21 15-4.35-4.35a1.5 1.5 0 0 0-2.12 0L5 20"/></svg>
                            <p class="text-[10px] uppercase tracking-wider mt-1.5">No photo yet</p>
                        </div>
                    @endif

                    @if($log->journal_removed)
                        <span class="absolute top-1.5 left-1.5 inline-flex items-center px-1.5 py-0.5 rounded text-[10px] font-medium bg-red-50 text-red-600 border border-red-100">Removed</span>
                    @elseif($log->photo_required)
                        <span class="absolute top-1.5 left-1.5 inline-flex items-center px-1.5 py-0.5 rounded text-[10px] font-medium bg-amber-50 text-amber-700 border border-amber-100">Awaiting journal</span>
                    @endif

                    <span class="absolute bottom-1.5 right-1.5 inline-flex items-center px-1.5 py-0.5 rounded text-[10px] font-medium bg-gray-900/70 text-white tabular-nums">
                        {{ number_format($log->hours_rendered, 2) }}h
                        @if($log->has_overtime)
                            · +{{ number_format($log->overtime_hours, 2) }}h OT
                        @endif
                    </span>
                </div>

                {{-- Body: date + journal message + actions --}}
                <div class="px-3 py-2.5 flex flex-col flex-1">
                    <div class="flex items-center justify-between gap-2">
                        <p class="text-xs font-semibold text-gray-900 whitespace-nowrap">{{ $log->date->format('M d, Y') }}</p>
                        <span class="text-[10px] font-medium uppercase tracking-wider text-gray-400">{{ $log->date->format('D') }}</span>
                    </div>

                    @if($log->has_journal)
                        <p class="text-xs text-gray-600 mt-1 whitespace-pre-line line-clamp-3 flex-1" title="{{ $log->notes }}">{{ $log->notes }}</p>
                    @elseif($log->journal_removed)
                        <p class="text-xs text-gray-400 italic mt-1 flex-1">Journal removed — upload a new one to bring the day back.</p>
                    @elseif($log->clocked_out && $log->ojtEnrollment && $log->ojtEnrollment->id === $intern->currentEnrollment?->id)
                        <p class="text-xs text-amber-700 mt-1 flex-1">You haven't uploaded a journal for this day yet.</p>
                    @else
                        <p class="text-xs text-gray-400 italic mt-1 flex-1">No journal uploaded for this day.</p>
                    @endif

                    <div class="mt-2 pt-2 border-t border-gray-100 flex items-center justify-between gap-2">
                        <div class="flex items-center gap-2 min-w-0">
                            @if($editable)
                                <button type="button" onclick="toggleJournalForm({{ $log->id }})"
                                    class="inline-flex items-center gap-1 rounded-md border border-brand-200 bg-brand-50 px-2 py-1 text-[11px] font-medium text-brand-700 hover:bg-brand-100 transition whitespace-nowrap">
                                    <svg width="11" height="11" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 20h9"/><path d="M16.5 3.5a2.12 2.12 0 0 1 3 3L7 19l-4 1 1-4Z"/></svg>
                                    {{ $log->has_journal ? 'Edit journal' : 'Upload journal' }}
                                </button>
                            @elseif($log->ojtEnrollment && $log->ojtEnrollment->id === $intern->currentEnrollment?->id && ! $log->clocked_out)
                                <span class="text-[10px] text-gray-400 whitespace-nowrap">Unlocks after clock-out</span>
                            @endif
                            @if($log->journal_removed && $editable)
                                <form method="POST" action="{{ route('intern.photo.restore', $log) }}">
                                    @csrf
                                    <button type="submit" class="inline-flex items-center gap-1 rounded-md border border-gray-200 bg-white px-2 py-1 text-[11px] font-medium text-gray-600 hover:bg-gray-50 transition whitespace-nowrap" title="Undo the removal">
                                        Restore
                                    </button>
                                </form>
                            @endif
                        </div>
                        @if($log->has_journal && $editable)
                            <button type="button" data-archive-url="{{ route('intern.photo.destroy', $log) }}"
                                data-forever-url="{{ route('intern.photo.force-destroy', $log) }}"
                                data-date="{{ $log->date->format('M d, Y') }}"
                                onclick="openDeleteJournal(this)"
                                class="inline-flex items-center justify-center w-6 h-6 rounded-md border border-gray-200 bg-white text-gray-400 hover:text-red-600 hover:border-red-200 hover:bg-red-50 transition"
                                title="Delete journal">
                                <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M3 6h18"/><path d="M8 6V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2m3 0-1 14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2L4 6"/></svg>
                            </button>
                        @endif
                    </div>

                    @if($editable)
                        <form id="journalForm{{ $log->id }}" method="POST" action="{{ route('intern.photo.store', $log) }}"
                            enctype="multipart/form-data" data-offline-queue="{{ $log->id }}"
                            class="hidden mt-2.5 rounded-lg bg-gray-50 border border-gray-100 p-2.5 space-y-2">
                            @csrf
                            <div>
                                <label class="block text-[10px] font-medium text-gray-600 mb-0.5">Duty photo @unless($log->has_journal)<span class="text-red-500">*</span>@endunless</label>
                                <input type="file" name="photo" accept="image/*" @unless($log->has_journal) required @endunless
                                    class="block w-full text-[11px] text-gray-500 file:mr-2 file:py-1 file:px-2 file:rounded-md file:border-0 file:bg-gray-900 file:text-white file:text-[11px] file:font-medium hover:file:bg-gray-800 file:cursor-pointer">
                                @error('photo') <p class="text-xs text-red-600 mt-1">{{ $message }}</p> @enderror
                            </div>
                            <div>
                                <label class="block text-[10px] font-medium text-gray-600 mb-0.5">Journal message <span class="text-red-500">*</span></label>
                                <textarea name="notes" rows="2" required maxlength="2000" placeholder="What did you do this day?"
                                    class="w-full px-2.5 py-1.5 rounded-md border-gray-200 text-xs placeholder:text-gray-300 focus:border-brand-400 focus:ring-2 focus:ring-brand-500/15 transition">{{ old('notes', $log->notes) }}</textarea>
                                @error('notes') <p class="text-xs text-red-600 mt-1">{{ $message }}</p> @enderror
                            </div>
                            <div class="flex items-center gap-2">
                                <button type="submit" class="bg-brand-600 hover:bg-brand-700 text-white text-[11px] font-medium px-2.5 py-1 rounded-md transition">Save journal</button>
                                <button type="button" onclick="toggleJournalForm({{ $log->id }})" class="text-[11px] font-medium text-gray-500 hover:text-gray-900">Cancel</button>
                            </div>
                        </form>
                    @endif
                </div>
            </div>
        @endforeach
    </div>

    <div class="mt-6">{{ $days->links() }}</div>
@else
    <div class="bg-white border border-gray-200 rounded-xl px-6 py-16 text-center">
        <p class="text-sm text-gray-400 mb-1">No duty days recorded in your current OJT set yet.</p>
        <p class="text-xs text-gray-400">Time in at the office scanner to start your documentation.</p>
    </div>
@endif

@if($archivedJournals->isNotEmpty())
    <div id="archiveSection" class="hidden mt-8">
        <div class="flex flex-wrap items-center justify-between gap-2 mb-4">
            <h2 class="text-sm font-semibold text-gray-900">Archived journals</h2>
            <p class="text-xs text-gray-400">Removed journals live here — restore them, or delete one permanently.</p>
        </div>
        <div class="bg-white border border-gray-200 rounded-xl divide-y divide-gray-100">
            @foreach($archivedJournals as $log)
                @php $archivedEditable = $log->ojtEnrollment && $log->ojtEnrollment->id === $intern->currentEnrollment?->id; @endphp
                <div class="px-4 py-3 flex flex-wrap sm:flex-nowrap items-center gap-3">
                    @if($log->photo_path)
                        <img src="{{ $log->photo_url }}" alt="Archived duty photo" class="w-12 h-12 object-cover rounded-md border border-gray-200 shrink-0">
                    @else
                        <div class="w-12 h-12 rounded-md border border-gray-200 bg-gray-50 flex items-center justify-center text-gray-300 shrink-0">
                            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="3" width="18" height="18" rx="2"/><circle cx="9" cy="9" r="2"/><path d="m21 15-4.35-4.35a1.5 1.5 0 0 0-2.12 0L5 20"/></svg>
                        </div>
                    @endif
                    <div class="min-w-0 flex-1">
                        <p class="text-sm font-medium text-gray-900">{{ $log->date->format('M d, Y') }}
                            @if($log->ojtEnrollment && $log->ojtEnrollment->id !== $intern->currentEnrollment?->id)
                                <span class="text-[11px] text-gray-400 font-normal">· {{ $log->ojtEnrollment->label }}</span>
                            @endif
                        </p>
                        @if($log->notes)
                            <p class="text-xs text-gray-500 truncate">{{ $log->notes }}</p>
                        @endif
                    </div>
                    <div class="flex items-center gap-2 shrink-0">
                        @if($archivedEditable)
                            <form method="POST" action="{{ route('intern.photo.restore', $log) }}">
                                @csrf
                                <button type="submit" class="inline-flex items-center gap-1 rounded-md border border-gray-200 bg-white px-2 py-1 text-[11px] font-medium text-gray-600 hover:bg-gray-50 transition">
                                    Restore
                                </button>
                            </form>
                            <form method="POST" action="{{ route('intern.photo.force-destroy', $log) }}"
                                data-confirm-title="Delete forever"
                                data-confirm-message="Permanently delete {{ $log->date->format('M d, Y') }}'s journal? The photo and notes are erased for good — this cannot be undone."
                                data-confirm-action="Delete forever"
                                onsubmit="return askConfirm(this);">
                                @csrf
                                @method('DELETE')
                                <button type="submit" class="inline-flex items-center gap-1 rounded-md border border-red-100 bg-white px-2 py-1 text-[11px] font-medium text-red-600 hover:bg-red-50 transition">
                                    Delete forever
                                </button>
                            </form>
                        @else
                            <span class="text-[11px] text-gray-400 whitespace-nowrap">Completed set — read-only</span>
                        @endif
                    </div>
                </div>
            @endforeach
        </div>
    </div>
@endif

@if($archivePhotos->isNotEmpty())
    <h2 class="text-sm font-semibold text-gray-900 mt-10 mb-4">Previous sets — read-only archive</h2>
    <div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-4 2xl:grid-cols-5 gap-4">
        @foreach($archivePhotos as $log)
            <div class="bg-white border border-gray-200 rounded-xl overflow-hidden group">
                <a href="{{ $log->photo_url }}" target="_blank" title="View full photo — {{ $log->date->format('M d, Y') }}">
                    <img src="{{ $log->photo_url }}" alt="Duty photo {{ $log->date->format('M d, Y') }}"
                         class="w-full aspect-square object-cover group-hover:opacity-90 transition">
                </a>
                <div class="px-3.5 py-2.5">
                    <p class="text-sm font-medium text-gray-900">{{ $log->date->format('M d, Y') }}</p>
                    <p class="text-[11px] text-gray-400">
                        {{ number_format($log->hours_rendered, 2) }}h
                        @if($log->ojtEnrollment) · {{ $log->ojtEnrollment->label }} @endif
                    </p>
                    <a href="{{ route('intern.report.show', $log) }}" class="text-[11px] font-medium text-brand-600 hover:underline">Daily Report</a>
                </div>
            </div>
        @endforeach
    </div>
@endif

@include('partials.confirm-modal')

{{-- Two-choice delete: Archive (restorable) or Delete forever (permanent). --}}
<div id="deleteJournalModal" class="hidden fixed inset-0 z-50 flex items-center justify-center bg-gray-900/25 px-4"
     onclick="if(event.target === this) closeDeleteJournal()">
    <div class="bg-white border border-gray-200 rounded-xl shadow-xl w-full max-w-sm">
        <div class="px-5 py-4 border-b border-gray-100">
            <h2 class="font-semibold text-gray-900">Delete journal</h2>
            <p class="text-xs text-gray-400 mt-0.5">{{ $intern->full_name }} &middot; <span id="deleteJournalDate"></span></p>
        </div>
        <div class="px-5 py-4">
            <p class="text-sm text-gray-600">What should happen to this day's journal? Your duty times stay either way.</p>
            <div class="mt-4 flex flex-col gap-2">
                <form id="journalArchiveForm" method="POST" action="">
                    @csrf
                    @method('DELETE')
                    <button type="submit" class="w-full inline-flex items-center justify-center gap-1.5 bg-white border border-gray-200 hover:bg-gray-50 text-gray-700 text-sm font-medium px-3 py-2 rounded-lg transition">
                        <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="2" y="4" width="20" height="5" rx="1"/><path d="M4 9v9a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V9"/><path d="M10 13h4"/></svg>
                        Archive — keep it restorable
                    </button>
                </form>
                <form id="journalForeverForm" method="POST" action="">
                    @csrf
                    @method('DELETE')
                    <button type="submit" class="w-full inline-flex items-center justify-center gap-1.5 bg-red-600 hover:bg-red-700 text-white text-sm font-medium px-3 py-2 rounded-lg transition">
                        <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M3 6h18"/><path d="M8 6V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2m3 0-1 14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2L4 6"/></svg>
                        Delete forever — permanent
                    </button>
                </form>
                <button type="button" onclick="closeDeleteJournal()" class="text-xs font-medium text-gray-500 hover:text-gray-900 py-1">Cancel</button>
            </div>
        </div>
    </div>
</div>

@push('scripts')
<script>
function toggleJournalForm(id) {
    document.getElementById('journalForm' + id).classList.toggle('hidden');
}

// The two-choice delete: the card's delete button carries both routes, and
// the modal's Archive / Delete-forever forms point at whichever was chosen.
function openDeleteJournal(button) {
    document.getElementById('deleteJournalDate').textContent = button.dataset.date;
    document.getElementById('journalArchiveForm').action = button.dataset.archiveUrl;
    document.getElementById('journalForeverForm').action = button.dataset.foreverUrl;
    document.getElementById('deleteJournalModal').classList.remove('hidden');
}

function closeDeleteJournal() {
    document.getElementById('deleteJournalModal').classList.add('hidden');
}

function toggleArchiveSection() {
    document.getElementById('archiveSection').classList.toggle('hidden');
}
</script>
@endpush
@endsection
