@extends('layouts.app')

@section('title', 'Coordinator Requests')

@section('content')
<div>
    <div class="mb-8">
        <h1 class="text-xl font-semibold tracking-tight text-gray-900">Coordinator Requests</h1>
        <p class="text-sm text-gray-500 mt-0.5">Ask your coordinator to apply to another office, or request a consultation — online or face-to-face. You'll be notified when it is decided.</p>
    </div>

    {{-- New request --}}
    <div class="bg-white border border-gray-200 rounded-xl p-5 mb-8">
        <h2 class="text-sm font-semibold text-gray-900 mb-4">Raise a request</h2>
        <form method="POST" action="{{ route('intern.coordinator-requests.store') }}" class="space-y-4">
            @csrf
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                <div>
                    <label for="type" class="block text-sm font-medium text-gray-700 mb-1.5">Type</label>
                    <select name="type" id="type"
                        onchange="document.getElementById('officeBlock').classList.toggle('hidden', this.value !== 'office_transfer');
                                  document.getElementById('consultBlock').classList.toggle('hidden', this.value !== 'consultation');"
                        class="w-full px-3 py-2 rounded-lg border-gray-200 text-sm focus:border-brand-400 focus:ring-2 focus:ring-brand-500/15 transition">
                        <option value="office_transfer" {{ old('type', 'office_transfer') === 'office_transfer' ? 'selected' : '' }}>Office transfer — apply to another office</option>
                        <option value="consultation" {{ old('type') === 'consultation' ? 'selected' : '' }}>Consultation — talk to my coordinator or supervisor</option>
                    </select>
                </div>

                <div id="officeBlock" {{ old('type') === 'consultation' ? 'class=hidden' : '' }}>
                    <label for="office_id" class="block text-sm font-medium text-gray-700 mb-1.5">Office to apply to</label>
                    <select name="office_id" id="office_id"
                        class="w-full px-3 py-2 rounded-lg border-gray-200 text-sm focus:border-brand-400 focus:ring-2 focus:ring-brand-500/15 transition">
                        <option value="">Select office…</option>
                        @foreach($offices as $office)
                            <option value="{{ $office->id }}" {{ old('office_id') == $office->id ? 'selected' : '' }}>{{ $office->name }}</option>
                        @endforeach
                    </select>
                    @error('office_id') <p class="text-xs text-red-600 mt-1">{{ $message }}</p> @enderror
                </div>

                <div id="consultBlock" class="hidden">
                    <label for="mode" class="block text-sm font-medium text-gray-700 mb-1.5">How to meet</label>
                    <select name="mode" id="mode"
                        class="w-full px-3 py-2 rounded-lg border-gray-200 text-sm focus:border-brand-400 focus:ring-2 focus:ring-brand-500/15 transition">
                        <option value="online" {{ old('mode', 'online') === 'online' ? 'selected' : '' }}>Online</option>
                        <option value="f2f" {{ old('mode') === 'f2f' ? 'selected' : '' }}>Face-to-face</option>
                    </select>
                    @error('mode') <p class="text-xs text-red-600 mt-1">{{ $message }}</p> @enderror
                </div>
            </div>

            @if($supervisors->isNotEmpty())
                <div id="recipientRow" class="hidden">
                    <label for="recipient_id" class="block text-sm font-medium text-gray-700 mb-1.5">Address it to <span class="text-gray-400 font-normal text-xs">(default: your coordinator)</span></label>
                    <select name="recipient_id" id="recipient_id"
                        class="w-full sm:w-80 px-3 py-2 rounded-lg border-gray-200 text-sm focus:border-brand-400 focus:ring-2 focus:ring-brand-500/15 transition">
                        <option value="">My coordinator</option>
                        @foreach($supervisors as $supervisor)
                            <option value="{{ $supervisor->id }}" {{ old('recipient_id') == $supervisor->id ? 'selected' : '' }}>{{ $supervisor->full_name }} (Supervisor)</option>
                        @endforeach
                    </select>
                </div>
            @endif

            <div>
                <label for="message" class="block text-sm font-medium text-gray-700 mb-1.5">Message</label>
                <textarea name="message" id="message" rows="3" required maxlength="2000"
                    placeholder="Why are you asking? Give the context your coordinator needs…"
                    class="w-full px-3 py-2 rounded-lg border-gray-200 text-sm placeholder:text-gray-300 focus:border-brand-400 focus:ring-2 focus:ring-brand-500/15 transition">{{ old('message') }}</textarea>
                @error('message') <p class="text-xs text-red-600 mt-1">{{ $message }}</p> @enderror
            </div>

            <div class="flex justify-end">
                <button type="submit" class="bg-brand-600 hover:bg-brand-700 text-white text-sm font-medium px-4 py-2 rounded-lg transition">Send request</button>
            </div>
        </form>
    </div>

    {{-- Request history --}}
    <div class="bg-white border border-gray-200 rounded-xl divide-y divide-gray-100">
        @forelse($requests as $request)
            <div class="px-5 py-4">
                <div class="flex flex-wrap items-start justify-between gap-3">
                    <div class="min-w-0 flex-1">
                        <div class="flex flex-wrap items-center gap-2">
                            <p class="text-sm font-medium text-gray-900">{{ $request->typeLabel() }}</p>
                            @if($request->mode)
                                <span class="inline-flex items-center rounded-full bg-brand-50 border border-brand-100 px-2 py-0.5 text-[11px] font-medium text-brand-700">{{ \App\Models\InternRequest::MODE_LABELS[$request->mode] }}</span>
                            @endif
                            @if($request->status === 'pending')
                                <span class="inline-flex items-center rounded-full bg-amber-50 border border-amber-100 px-2 py-0.5 text-[11px] font-medium text-amber-700">Pending</span>
                            @elseif($request->status === 'approved')
                                <span class="inline-flex items-center rounded-full bg-emerald-50 border border-emerald-100 px-2 py-0.5 text-[11px] font-medium text-emerald-700">✓ Approved</span>
                            @else
                                <span class="inline-flex items-center rounded-full bg-red-50 border border-red-100 px-2 py-0.5 text-[11px] font-medium text-red-700">✕ Rejected</span>
                            @endif
                        </div>

                        @if($request->office)
                            <p class="text-xs text-gray-500 mt-1">Applying to: {{ $request->office->name }}</p>
                        @endif

                        <p class="text-sm text-gray-600 mt-1.5 leading-relaxed">{{ $request->message }}</p>

                        @if($request->status !== 'pending' && $request->decision_remarks)
                            <p class="text-xs mt-1.5 {{ $request->status === 'rejected' ? 'text-red-600' : 'text-emerald-700' }}">
                                <span class="font-semibold">{{ $request->decider?->full_name }}'s remarks:</span> {{ $request->decision_remarks }}
                            </p>
                        @endif

                        <p class="text-[11px] text-gray-400 mt-1">
                            {{ $request->created_at->format('M d, Y') }}
                            @if($request->decided_at) · decided {{ $request->decided_at->format('M d, Y') }} @endif
                        </p>
                    </div>

                    @if($request->status === 'pending')
                        <form method="POST" action="{{ route('intern.coordinator-requests.destroy', $request) }}"
                            data-confirm-title="Withdraw request"
                            data-confirm-message="Withdraw this {{ strtolower($request->typeLabel()) }} request?"
                            data-confirm-action="Withdraw"
                            onsubmit="return askConfirm(this);">
                            @csrf
                            @method('DELETE')
                            <button type="submit" class="text-xs font-medium text-gray-400 hover:text-red-600 px-2 py-1 rounded-md hover:bg-red-50 transition shrink-0">Withdraw</button>
                        </form>
                    @endif
                </div>
            </div>
        @empty
            <div class="px-5 py-14 text-center">
                <p class="text-sm text-gray-400">No requests yet — file one above when you need a transfer or a consultation.</p>
            </div>
        @endforelse
    </div>
</div>

@include('partials.confirm-modal')

@push('scripts')
<script>
// The recipient row applies to consultations only.
document.getElementById('type').addEventListener('change', function () {
    document.getElementById('recipientRow')?.classList.toggle('hidden', this.value !== 'consultation');
});
if (document.getElementById('type').value === 'consultation') {
    document.getElementById('recipientRow')?.classList.remove('hidden');
}
</script>
@endpush
@endsection
