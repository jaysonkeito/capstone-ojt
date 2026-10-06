@php
    /** @var \App\Models\OjtLog $log */
    /** @var \App\Models\User $intern */
    $kioskCaptures = collect($log->kiosk_captures ?? []);
@endphp
@if($kioskCaptures->isNotEmpty())
    {{-- Webcam snapshots the kiosk took of the intern at scan time — the
         supervisor's/coordinator's way to confirm who made each entry. --}}
    <button type="button"
        onclick="document.getElementById('kioskCaptures{{ $log->id }}').classList.remove('hidden')"
        class="inline-flex items-center gap-1 text-[11px] font-medium text-gray-500 hover:text-brand-600 transition"
        title="Kiosk webcam snapshots for this day">
        <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M14.5 4h-5L7 7H4a2 2 0 0 0-2 2v9a2 2 0 0 0 2 2h16a2 2 0 0 0 2-2V9a2 2 0 0 0-2-2h-3l-2.5-3z"/><circle cx="12" cy="13" r="3"/></svg>
        {{ $kioskCaptures->count() }}
    </button>

    <div id="kioskCaptures{{ $log->id }}" class="hidden fixed inset-0 z-50 flex items-center justify-center p-4">
        <div class="absolute inset-0 bg-gray-900/40" onclick="document.getElementById('kioskCaptures{{ $log->id }}').classList.add('hidden')"></div>
        <div class="relative bg-white border border-gray-200 rounded-xl shadow-xl w-full max-w-2xl overflow-hidden">
            <div class="flex items-center justify-between gap-2 px-5 py-3 border-b border-gray-100">
                <p class="text-sm font-medium text-gray-900">Scan captures — {{ $intern->full_name }} ({{ $log->date->format('M d, Y') }})</p>
                <button type="button" onclick="document.getElementById('kioskCaptures{{ $log->id }}').classList.add('hidden')"
                    class="w-8 h-8 flex items-center justify-center rounded-md text-gray-400 hover:text-gray-900 hover:bg-gray-100">
                    <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M18 6 6 18M6 6l12 12"/></svg>
                </button>
            </div>
            <div class="grid grid-cols-2 sm:grid-cols-3 gap-4 p-5 max-h-[70vh] overflow-y-auto">
                @foreach($kioskCaptures as $slot => $path)
                    <figure>
                        <img src="{{ \Illuminate\Support\Facades\Storage::disk('public')->url($path) }}"
                            alt="Kiosk capture for {{ \App\Support\AttendanceRecorder::labelFor($slot) }}" loading="lazy"
                            class="w-full aspect-[4/3] object-cover rounded-lg ring-1 ring-gray-200">
                        <figcaption class="mt-1.5 text-[11px] font-medium text-gray-500">{{ \App\Support\AttendanceRecorder::labelFor($slot) }}</figcaption>
                    </figure>
                @endforeach
            </div>
        </div>
    </div>
@endif
