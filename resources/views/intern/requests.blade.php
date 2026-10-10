@extends('layouts.app')

@section('title', 'My Requests')

@section('content')
<div>
    <div class="mb-8">
        <h1 class="text-xl font-semibold tracking-tight text-gray-900">My Requests</h1>
        <p class="text-sm text-gray-500 mt-0.5">Something wrong with a recorded day, or a day you were absent? Ask your supervisor or coordinator here — nothing on your record changes until they approve.</p>
    </div>

    {{-- New request --}}
    <div class="bg-white border border-gray-200 rounded-xl p-5 mb-8">
        <h2 class="text-sm font-semibold text-gray-900 mb-4">Raise a request</h2>
        <form method="POST" action="{{ route('intern.requests.store') }}" enctype="multipart/form-data" class="space-y-4">
            @csrf
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                <div>
                    <label for="type" class="block text-sm font-medium text-gray-700 mb-1.5">Type</label>
                    <select name="type" id="type"
                        onchange="document.getElementById('timesBlock').classList.toggle('hidden', this.value !== 'correction')"
                        class="w-full px-3 py-2 rounded-lg border-gray-200 text-sm focus:border-brand-400 focus:ring-2 focus:ring-brand-500/15 transition">
                        <option value="correction" {{ old('type', 'correction') === 'correction' ? 'selected' : '' }}>Correction — my recorded times are wrong</option>
                        <option value="absence" {{ old('type') === 'absence' ? 'selected' : '' }}>Absence report — I was absent that day</option>
                    </select>
                </div>
                <div>
                    <label for="date" class="block text-sm font-medium text-gray-700 mb-1.5">Duty date</label>
                    <input type="date" name="date" id="date" value="{{ old('date', today()->format('Y-m-d')) }}" max="{{ today()->format('Y-m-d') }}" required
                        class="w-full px-3 py-2 rounded-lg border-gray-200 text-sm focus:border-brand-400 focus:ring-2 focus:ring-brand-500/15 transition">
                </div>
            </div>
            @error('date') <p class="text-xs text-red-600 -mt-2">{{ $message }}</p> @enderror

            <div id="timesBlock" {{ old('type') === 'absence' ? 'class=hidden' : '' }}>
                <div class="grid grid-cols-2 sm:grid-cols-4 gap-3">
                    @foreach(['am_time_in' => 'AM In', 'am_time_out' => 'AM Out', 'am_time_in_2' => 'AM In (2)', 'am_time_out_2' => 'AM Out (2)', 'pm_time_in' => 'PM In', 'pm_time_out' => 'PM Out', 'pm_time_in_2' => 'PM In (2)', 'pm_time_out_2' => 'PM Out (2)'] as $field => $label)
                        <div>
                            <label for="{{ $field }}" class="block text-xs font-medium text-gray-600 mb-1">{{ $label }}</label>
                            <input type="time" name="{{ $field }}" id="{{ $field }}" value="{{ old($field) }}"
                                class="w-full px-3 py-2 rounded-lg border-gray-200 text-sm focus:border-brand-400 focus:ring-2 focus:ring-brand-500/15 transition">
                        </div>
                    @endforeach
                </div>
                <p class="text-[11px] text-gray-400 mt-2">Enter the times the entry should carry — at least one. On approval, your entry is updated and the hours recomputed.</p>
                @error('am_time_in') <p class="text-xs text-red-600 mt-1">{{ $message }}</p> @enderror
            </div>

            <div>
                <label for="reason" class="block text-sm font-medium text-gray-700 mb-1.5">Reason</label>
                <textarea name="reason" id="reason" rows="3" required placeholder="What happened, and why the record should change…"
                    class="w-full px-3 py-2 rounded-lg border-gray-200 text-sm placeholder:text-gray-300 focus:border-brand-400 focus:ring-2 focus:ring-brand-500/15 transition">{{ old('reason') }}</textarea>
            </div>

            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1.5">Proof <span class="text-gray-400 font-normal text-xs">(optional — up to 2 photos: an excuse note, the corrected entry, anything that shows the day)</span></label>
                <input type="file" name="proofs[]" multiple accept="image/*" capture="environment"
                    class="w-full text-sm text-gray-600 file:mr-3 file:py-2 file:px-3 file:rounded-lg file:border-0 file:bg-brand-50 file:text-brand-700 file:text-xs file:font-medium hover:file:bg-brand-100">
                @error('proofs') <p class="text-xs text-red-600 mt-1">{{ $message }}</p> @enderror
                @error('proofs.0') <p class="text-xs text-red-600 mt-1">{{ $message }}</p> @enderror
                @error('proofs.1') <p class="text-xs text-red-600 mt-1">{{ $message }}</p> @enderror
            </div>

            <button type="submit" class="bg-brand-600 hover:bg-brand-700 text-white text-sm font-medium px-4 py-2.5 rounded-lg transition">Submit request</button>
        </form>
    </div>

    {{-- My requests --}}
    <div class="bg-white border border-gray-200 rounded-xl">
        <div class="px-5 py-3.5 border-b border-gray-100"><h2 class="text-sm font-semibold text-gray-900">My requests</h2></div>
        <div class="divide-y divide-gray-100">
            @forelse($requests as $lr)
                <div class="px-5 py-4">
                    <div class="flex flex-wrap items-center justify-between gap-2">
                        <div class="min-w-0">
                            <p class="text-sm text-gray-900">
                                <span class="inline-flex items-center px-2 py-0.5 rounded-md text-[11px] font-medium mr-1 {{ $lr->type === \App\Models\LogRequest::TYPE_CORRECTION ? 'bg-brand-50 text-brand-700' : 'bg-gray-100 text-gray-600' }}">{{ $lr->type_label }}</span>
                                {{ $lr->date->format('M d, Y') }}
                            </p>
                            <p class="text-sm text-gray-500 mt-0.5">“{{ $lr->reason }}”</p>
                            @if($lr->proof_paths)
                                <div class="flex gap-2 mt-2">
                                    @foreach($lr->proof_paths as $proof)
                                        <a href="{{ \Illuminate\Support\Facades\Storage::disk('public')->url($proof) }}" target="_blank" rel="noopener">
                                            <img src="{{ \Illuminate\Support\Facades\Storage::disk('public')->url($proof) }}" loading="lazy"
                                                alt="Proof attachment" class="w-14 h-14 rounded-lg object-cover border border-gray-200 hover:ring-2 hover:ring-brand-400 transition">
                                        </a>
                                    @endforeach
                                </div>
                            @endif
                            @if($lr->status !== 'pending')
                                <p class="text-xs text-gray-400 mt-1">
                                    {{ $lr->decidedBy?->full_name ?? 'Decided' }} · {{ $lr->decided_at?->format('M d, Y') }}
                                    @if($lr->decision_comment) · “{{ $lr->decision_comment }}” @endif
                                </p>
                            @endif
                        </div>
                        <div class="shrink-0 flex flex-col items-end gap-1.5">
                            <span class="inline-flex items-center px-2 py-0.5 rounded-md text-xs font-medium {{ $lr->status_badge['class'] }}">{{ $lr->status_badge['label'] }}</span>
                            @if($lr->status === 'pending')
                                <form method="POST" action="{{ route('intern.requests.destroy', $lr) }}"
                                    data-confirm-title="Withdraw request"
                                    data-confirm-message="Withdraw your {{ $lr->type_label }} for {{ $lr->date->format('M d, Y') }}? Your supervisor and coordinator will no longer see it."
                                    data-confirm-action="Withdraw"
                                    onsubmit="return askConfirm(this);">
                                    @csrf
                                    @method('DELETE')
                                    <button class="text-[11px] font-medium text-gray-400 hover:text-red-600 transition">Withdraw</button>
                                </form>
                            @endif
                        </div>
                    </div>
                </div>
            @empty
                <p class="px-5 py-10 text-center text-sm text-gray-400">No requests yet.</p>
            @endforelse
        </div>
    </div>
</div>

@include('partials.confirm-modal')
@endsection
