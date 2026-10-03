<div id="editModal{{ $log->id }}" class="hidden fixed inset-0 z-50 flex items-center justify-center bg-gray-900/25 px-4"
     onclick="if(event.target === this) this.classList.add('hidden')">
    <div class="bg-white border border-gray-200 rounded-xl shadow-xl w-full max-w-md max-h-[90vh] overflow-y-auto">
        <div class="flex items-center justify-between px-6 py-4 border-b border-gray-100">
            <div>
                <h2 class="font-semibold text-gray-900">Edit Entry</h2>
                <div class="text-xs text-gray-400 mt-0.5 flex items-center gap-1.5">
                    @if(! empty($olderLog))
                        <button type="button" onclick="switchEditModal({{ $log->id }}, {{ $olderLog->id }})"
                            class="inline-flex items-center justify-center w-5 h-5 rounded-md text-gray-400 hover:text-gray-900 hover:bg-gray-100 transition"
                            title="Previous day — {{ $olderLog->date->format('M d, Y') }}">
                            <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M15 18l-6-6 6-6"/></svg>
                        </button>
                    @endif
                    <span>{{ $log->user->full_name }} &middot; {{ $log->date->format('M d, Y') }}</span>
                    @if(! empty($newerLog))
                        <button type="button" onclick="switchEditModal({{ $log->id }}, {{ $newerLog->id }})"
                            class="inline-flex items-center justify-center w-5 h-5 rounded-md text-gray-400 hover:text-gray-900 hover:bg-gray-100 transition"
                            title="Next day — {{ $newerLog->date->format('M d, Y') }}">
                            <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M9 18l6-6-6-6"/></svg>
                        </button>
                    @endif
                </div>
            </div>
            <button type="button" onclick="document.getElementById('editModal{{ $log->id }}').classList.add('hidden')" class="text-gray-400 hover:text-gray-900">
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M18 6 6 18M6 6l12 12"/></svg>
            </button>
        </div>

        <form method="POST" action="{{ route('admin.logs.update', $log) }}" class="px-6 py-5 space-y-4">
            @csrf
            @method('PUT')

            <div class="grid grid-cols-2 gap-3">
                <div>
                    <label class="block text-xs font-medium text-gray-600 mb-1">AM In</label>
                    <input type="time" name="am_time_in" value="{{ $log->am_time_in ? substr($log->am_time_in, 0, 5) : '' }}" class="w-full px-3 py-2 rounded-lg border-gray-200 text-sm focus:border-brand-400 focus:ring-2 focus:ring-brand-500/15 transition">
                </div>
                <div>
                    <label class="block text-xs font-medium text-gray-600 mb-1">AM Out</label>
                    <input type="time" name="am_time_out" value="{{ $log->am_time_out ? substr($log->am_time_out, 0, 5) : '' }}" class="w-full px-3 py-2 rounded-lg border-gray-200 text-sm focus:border-brand-400 focus:ring-2 focus:ring-brand-500/15 transition">
                </div>
                <div>
                    <label class="block text-xs font-medium text-gray-600 mb-1">PM In</label>
                    <input type="time" name="pm_time_in" value="{{ $log->pm_time_in ? substr($log->pm_time_in, 0, 5) : '' }}" class="w-full px-3 py-2 rounded-lg border-gray-200 text-sm focus:border-brand-400 focus:ring-2 focus:ring-brand-500/15 transition">
                </div>
                <div>
                    <label class="block text-xs font-medium text-gray-600 mb-1">PM Out</label>
                    <input type="time" name="pm_time_out" value="{{ $log->pm_time_out ? substr($log->pm_time_out, 0, 5) : '' }}" class="w-full px-3 py-2 rounded-lg border-gray-200 text-sm focus:border-brand-400 focus:ring-2 focus:ring-brand-500/15 transition">
                </div>
                <div>
                    <label class="block text-xs font-medium text-gray-600 mb-1">AM In (2)</label>
                    <input type="time" name="am_time_in_2" value="{{ $log->am_time_in_2 ? substr($log->am_time_in_2, 0, 5) : '' }}" class="w-full px-3 py-2 rounded-lg border-gray-200 text-sm focus:border-brand-400 focus:ring-2 focus:ring-brand-500/15 transition">
                </div>
                <div>
                    <label class="block text-xs font-medium text-gray-600 mb-1">AM Out (2)</label>
                    <input type="time" name="am_time_out_2" value="{{ $log->am_time_out_2 ? substr($log->am_time_out_2, 0, 5) : '' }}" class="w-full px-3 py-2 rounded-lg border-gray-200 text-sm focus:border-brand-400 focus:ring-2 focus:ring-brand-500/15 transition">
                </div>
                <div>
                    <label class="block text-xs font-medium text-gray-600 mb-1">PM In (2)</label>
                    <input type="time" name="pm_time_in_2" value="{{ $log->pm_time_in_2 ? substr($log->pm_time_in_2, 0, 5) : '' }}" class="w-full px-3 py-2 rounded-lg border-gray-200 text-sm focus:border-brand-400 focus:ring-2 focus:ring-brand-500/15 transition">
                </div>
                <div>
                    <label class="block text-xs font-medium text-gray-600 mb-1">PM Out (2)</label>
                    <input type="time" name="pm_time_out_2" value="{{ $log->pm_time_out_2 ? substr($log->pm_time_out_2, 0, 5) : '' }}" class="w-full px-3 py-2 rounded-lg border-gray-200 text-sm focus:border-brand-400 focus:ring-2 focus:ring-brand-500/15 transition">
                </div>
            </div>

            <p class="text-[11px] text-gray-400">The (2) pair records one stepped-out-and-back episode per session — e.g. AM In 8:00, AM Out 10:30, AM In (2) 11:00, AM Out (2) 12:00 — and the time away in between is not counted. Time outside the standard schedule auto-counts as overtime.</p>

            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1.5">Notes</label>
                <textarea name="notes" rows="3" placeholder="Optional notes…"
                    class="w-full px-3 py-2 rounded-lg border-gray-200 text-sm placeholder:text-gray-300 focus:border-brand-400 focus:ring-2 focus:ring-brand-500/15 transition">{{ $log->notes }}</textarea>
            </div>

            <button type="submit" class="w-full bg-brand-600 hover:bg-brand-700 text-white text-sm font-medium py-2.5 rounded-lg transition">
                Save Changes
            </button>
        </form>
    </div>
</div>
