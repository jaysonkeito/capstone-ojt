@extends('layouts.app')

@section('title', 'Log OJT Hours')

@section('content')
<div class="max-w-xl mx-auto">
    <div class="text-center mb-6">
        <h1 class="text-2xl font-bold text-navy-900">New Logbook Entry</h1>
        <p class="text-navy-400 text-sm mt-1">Overtime is detected automatically — no need to compute it yourself.</p>
    </div>

    <div class="bg-white rounded-2xl border border-navy-100 shadow-sm p-6 sm:p-8">
        <form method="POST" action="{{ route('admin.logs.store') }}" class="space-y-6">
            @csrf

            <div>
                <label class="block text-sm font-semibold text-navy-700 mb-1.5">Intern</label>
                <select name="user_id" required
                    class="w-full rounded-xl border-navy-200 text-sm focus:border-brand-500 focus:ring-brand-500 focus:ring-2 focus:ring-offset-0">
                    <option value="">Select intern...</option>
                    @foreach($interns as $i)
                        <option value="{{ $i->id }}" {{ old('user_id', $selectedIntern?->id) == $i->id ? 'selected' : '' }}>
                            {{ $i->full_name }} ({{ $i->student_id }})
                        </option>
                    @endforeach
                </select>
            </div>

            <div>
                <label class="block text-sm font-semibold text-navy-700 mb-1.5">Date</label>
                <input type="date" name="date" value="{{ old('date', now()->format('Y-m-d')) }}" required max="{{ now()->format('Y-m-d') }}"
                    class="w-full rounded-xl border-navy-200 text-sm focus:border-brand-500 focus:ring-brand-500 focus:ring-2 focus:ring-offset-0">
            </div>

            <div class="grid grid-cols-2 gap-4">
                <div>
                    <label class="block text-sm font-semibold text-navy-700 mb-1.5">🌅 AM IN</label>
                    <input type="time" name="am_time_in" value="{{ old('am_time_in') }}"
                        class="w-full rounded-xl border-navy-200 text-sm focus:border-brand-500 focus:ring-brand-500 focus:ring-2 focus:ring-offset-0">
                </div>
                <div>
                    <label class="block text-sm font-semibold text-navy-700 mb-1.5">🌤️ AM OUT</label>
                    <input type="time" name="am_time_out" value="{{ old('am_time_out') }}"
                        class="w-full rounded-xl border-navy-200 text-sm focus:border-brand-500 focus:ring-brand-500 focus:ring-2 focus:ring-offset-0">
                </div>
                <div>
                    <label class="block text-sm font-semibold text-navy-700 mb-1.5">🌇 PM IN</label>
                    <input type="time" name="pm_time_in" value="{{ old('pm_time_in') }}"
                        class="w-full rounded-xl border-navy-200 text-sm focus:border-brand-500 focus:ring-brand-500 focus:ring-2 focus:ring-offset-0">
                </div>
                <div>
                    <label class="block text-sm font-semibold text-navy-700 mb-1.5">🌙 PM OUT</label>
                    <input type="time" name="pm_time_out" value="{{ old('pm_time_out') }}"
                        class="w-full rounded-xl border-navy-200 text-sm focus:border-brand-500 focus:ring-brand-500 focus:ring-2 focus:ring-offset-0">
                </div>
                <div>
                    <label class="block text-sm font-semibold text-navy-700 mb-1.5">↩ AM IN (2)</label>
                    <input type="time" name="am_time_in_2" value="{{ old('am_time_in_2') }}"
                        class="w-full rounded-xl border-navy-200 text-sm focus:border-brand-500 focus:ring-brand-500 focus:ring-2 focus:ring-offset-0">
                </div>
                <div>
                    <label class="block text-sm font-semibold text-navy-700 mb-1.5">↩ AM OUT (2)</label>
                    <input type="time" name="am_time_out_2" value="{{ old('am_time_out_2') }}"
                        class="w-full rounded-xl border-navy-200 text-sm focus:border-brand-500 focus:ring-brand-500 focus:ring-2 focus:ring-offset-0">
                </div>
                <div>
                    <label class="block text-sm font-semibold text-navy-700 mb-1.5">↩ PM IN (2)</label>
                    <input type="time" name="pm_time_in_2" value="{{ old('pm_time_in_2') }}"
                        class="w-full rounded-xl border-navy-200 text-sm focus:border-brand-500 focus:ring-brand-500 focus:ring-2 focus:ring-offset-0">
                </div>
                <div>
                    <label class="block text-sm font-semibold text-navy-700 mb-1.5">↩ PM OUT (2)</label>
                    <input type="time" name="pm_time_out_2" value="{{ old('pm_time_out_2') }}"
                        class="w-full rounded-xl border-navy-200 text-sm focus:border-brand-500 focus:ring-brand-500 focus:ring-2 focus:ring-offset-0">
                </div>
            </div>

            <div class="flex items-start gap-2 bg-brand-50 border border-brand-100 rounded-xl px-4 py-3 text-xs text-brand-700">
                <span>⚡</span>
                <span>Clocking out after the standard PM Time Out automatically counts as overtime — no separate OT field needed.</span>
            </div>

            <div>
                <label class="block text-sm font-semibold text-navy-700 mb-1.5">Notes <span class="text-slate-400 font-normal">(optional)</span></label>
                <textarea name="notes" rows="3" placeholder="Activity or task summary for the day..."
                    class="w-full rounded-xl border-navy-200 text-sm focus:border-brand-500 focus:ring-brand-500 focus:ring-2 focus:ring-offset-0">{{ old('notes') }}</textarea>
            </div>

            <div class="flex gap-3 pt-2">
                <button type="submit" class="flex-1 flex items-center justify-center gap-2 bg-slate-900 hover:bg-slate-800 text-white text-sm font-semibold px-5 py-3 rounded-xl transition">
                    💾 Save Log Entry
                </button>
                @if($selectedIntern)
                    <a href="{{ route('admin.logs.show', $selectedIntern) }}" class="flex items-center justify-center text-sm font-medium px-5 py-3 rounded-xl text-navy-500 hover:bg-navy-100 border border-navy-100">History</a>
                @endif
            </div>
        </form>
    </div>
</div>
@endsection
