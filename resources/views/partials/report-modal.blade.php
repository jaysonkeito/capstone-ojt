@if($log->photo_path)
    <button type="button"
        onclick="document.getElementById('reportModal{{ $log->id }}').classList.remove('hidden'); var f = document.getElementById('reportFrame{{ $log->id }}'); if (!f.getAttribute('src')) f.setAttribute('src', '{{ route('reports.show', $log) }}');"
        class="inline-flex items-center gap-1 text-[11px] font-medium text-gray-500 hover:text-brand-600">
            <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><path d="M14 2v6h6"/><path d="M12 18v-6M9 15l3 3 3-3"/></svg>
        Daily Report
    </button>

    <div id="reportModal{{ $log->id }}" class="hidden fixed inset-0 z-50 flex items-center justify-center p-4">
        <div class="absolute inset-0 bg-gray-900/40" onclick="document.getElementById('reportModal{{ $log->id }}').classList.add('hidden')"></div>
        <div class="relative bg-white border border-gray-200 rounded-xl shadow-xl w-full max-w-3xl overflow-hidden">
            <div class="flex flex-wrap items-center justify-between gap-2 px-5 py-3 border-b border-gray-100">
                <p class="text-sm font-medium text-gray-900">Daily Report — {{ $intern->full_name }} ({{ $log->date->format('M d, Y') }})</p>
                <div class="flex items-center gap-2">
                    <a href="{{ route('reports.show', ['log' => $log, 'download' => 1]) }}"
                        class="inline-flex items-center gap-1.5 bg-brand-600 hover:bg-brand-700 text-white text-xs font-medium px-3 py-1.5 rounded-lg transition">
                        <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><path d="M7 10l5 5 5-5"/><path d="M12 15V3"/></svg>
                        Download
                    </a>
                    <button type="button" onclick="document.getElementById('reportModal{{ $log->id }}').classList.add('hidden')"
                        class="w-8 h-8 flex items-center justify-center rounded-md text-gray-400 hover:text-gray-900 hover:bg-gray-100">
                        <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M18 6 6 18M6 6l12 12"/></svg>
                    </button>
                </div>
            </div>
            <iframe id="reportFrame{{ $log->id }}" title="Daily Report preview"
                class="w-full h-[70vh] bg-gray-50"></iframe>
        </div>
    </div>
@endif
