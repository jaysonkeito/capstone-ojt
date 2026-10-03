<div id="newEntryModal" class="hidden fixed inset-0 z-50 flex items-center justify-center bg-gray-900/25 px-4"
     onclick="if(event.target === this) this.classList.add('hidden')">
    <div class="bg-white border border-gray-200 rounded-xl shadow-xl w-full max-w-md max-h-[90vh] overflow-y-auto">
        <div class="flex items-center justify-between px-6 py-4 border-b border-gray-100">
            <h2 class="font-semibold text-gray-900">New Logbook Entry</h2>
            <button type="button" onclick="document.getElementById('newEntryModal').classList.add('hidden')" class="text-gray-400 hover:text-gray-900">
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M18 6 6 18M6 6l12 12"/></svg>
            </button>
        </div>

        <form method="POST" action="{{ route('admin.logs.store') }}" class="px-6 py-5 space-y-4">
            @csrf
            <input type="hidden" name="date" value="{{ $date->format('Y-m-d') }}">

            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1.5">Intern</label>
                <select name="user_id" required class="w-full px-3 py-2 rounded-lg border-gray-200 text-sm focus:border-brand-400 focus:ring-2 focus:ring-brand-500/15 transition">
                    <option value="">Select intern</option>
                    @foreach($interns as $i)
                        <option value="{{ $i->id }}" {{ old('user_id') == $i->id ? 'selected' : '' }}>
                            {{ $i->full_name }} ({{ $i->student_id }})
                        </option>
                    @endforeach
                </select>
            </div>

            <div class="grid grid-cols-2 gap-3">
                <div>
                    <label class="block text-xs font-medium text-gray-600 mb-1">AM In</label>
                    <input type="time" name="am_time_in" value="{{ old('am_time_in') }}" class="w-full px-3 py-2 rounded-lg border-gray-200 text-sm focus:border-brand-400 focus:ring-2 focus:ring-brand-500/15 transition">
                </div>
                <div>
                    <label class="block text-xs font-medium text-gray-600 mb-1">AM Out</label>
                    <input type="time" name="am_time_out" value="{{ old('am_time_out') }}" class="w-full px-3 py-2 rounded-lg border-gray-200 text-sm focus:border-brand-400 focus:ring-2 focus:ring-brand-500/15 transition">
                </div>
                <div>
                    <label class="block text-xs font-medium text-gray-600 mb-1">PM In</label>
                    <input type="time" name="pm_time_in" value="{{ old('pm_time_in') }}" class="w-full px-3 py-2 rounded-lg border-gray-200 text-sm focus:border-brand-400 focus:ring-2 focus:ring-brand-500/15 transition">
                </div>
                <div>
                    <label class="block text-xs font-medium text-gray-600 mb-1">PM Out</label>
                    <input type="time" name="pm_time_out" value="{{ old('pm_time_out') }}" class="w-full px-3 py-2 rounded-lg border-gray-200 text-sm focus:border-brand-400 focus:ring-2 focus:ring-brand-500/15 transition">
                </div>
                <div>
                    <label class="block text-xs font-medium text-gray-600 mb-1">AM In (2)</label>
                    <input type="time" name="am_time_in_2" value="{{ old('am_time_in_2') }}" class="w-full px-3 py-2 rounded-lg border-gray-200 text-sm focus:border-brand-400 focus:ring-2 focus:ring-brand-500/15 transition">
                </div>
                <div>
                    <label class="block text-xs font-medium text-gray-600 mb-1">AM Out (2)</label>
                    <input type="time" name="am_time_out_2" value="{{ old('am_time_out_2') }}" class="w-full px-3 py-2 rounded-lg border-gray-200 text-sm focus:border-brand-400 focus:ring-2 focus:ring-brand-500/15 transition">
                </div>
                <div>
                    <label class="block text-xs font-medium text-gray-600 mb-1">PM In (2)</label>
                    <input type="time" name="pm_time_in_2" value="{{ old('pm_time_in_2') }}" class="w-full px-3 py-2 rounded-lg border-gray-200 text-sm focus:border-brand-400 focus:ring-2 focus:ring-brand-500/15 transition">
                </div>
                <div>
                    <label class="block text-xs font-medium text-gray-600 mb-1">PM Out (2)</label>
                    <input type="time" name="pm_time_out_2" value="{{ old('pm_time_out_2') }}" class="w-full px-3 py-2 rounded-lg border-gray-200 text-sm focus:border-brand-400 focus:ring-2 focus:ring-brand-500/15 transition">
                </div>
            </div>

            <p class="text-[11px] text-gray-400">Regular vs. overtime hours are auto-computed from these times against the standard working hours in Settings. The (2) pair is for a mid-session "stepped out and came back" (the away time in between isn't counted). You can save just one time (e.g. AM In) now and fill in the rest later.</p>

            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1.5">Notes</label>
                <textarea name="notes" rows="3" placeholder="Optional notes…"
                    class="w-full px-3 py-2 rounded-lg border-gray-200 text-sm placeholder:text-gray-300 focus:border-brand-400 focus:ring-2 focus:ring-brand-500/15 transition">{{ old('notes') }}</textarea>
            </div>

            <button type="submit" class="w-full bg-brand-600 hover:bg-brand-700 text-white text-sm font-medium py-2.5 rounded-lg transition">
                Save Entry
            </button>
        </form>
    </div>
</div>

@if($errors->any())
<script>document.getElementById('newEntryModal').classList.remove('hidden');</script>
@endif
