@extends('layouts.app')

@section('title', 'Add Missing Entry')

@section('content')
<div class="max-w-2xl mx-auto">
    <div class="mb-8">
        <a href="{{ route('monitor.dashboard') }}" class="text-xs font-medium text-gray-400 hover:text-gray-700">← My Interns</a>
        <h1 class="text-xl font-semibold tracking-tight text-gray-900 mt-1">Add Missing Entry</h1>
        <p class="text-sm text-gray-500 mt-0.5">
            Record a scan one of your office's interns missed. The entry is saved as
            <span class="inline-flex items-center px-1.5 py-0.5 rounded text-[11px] font-medium bg-amber-50 text-amber-700">pending review</span>
            for the System Admin to confirm — it notifies them right away.
        </p>
    </div>

    <form method="POST" action="{{ route('monitor.logs.store') }}" class="bg-white border border-gray-200 rounded-xl px-6 py-5 space-y-4">
        @csrf

        <div>
            <label for="user_id" class="block text-sm font-medium text-gray-700 mb-1.5">Intern</label>
            <select name="user_id" id="user_id" required
                class="w-full px-3 py-2 rounded-lg border-gray-200 text-sm focus:border-brand-400 focus:ring-2 focus:ring-brand-500/15 transition">
                <option value="">Select intern</option>
                @foreach($interns as $i)
                    <option value="{{ $i->id }}" {{ old('user_id') == $i->id ? 'selected' : '' }}>
                        {{ $i->full_name }} ({{ $i->student_id }})
                    </option>
                @endforeach
            </select>
            @error('user_id') <p class="text-xs text-red-600 mt-1">{{ $message }}</p> @enderror
        </div>

        <div>
            <label for="date" class="block text-sm font-medium text-gray-700 mb-1.5">Date</label>
            <input type="date" name="date" id="date" value="{{ old('date', today()->format('Y-m-d')) }}" max="{{ today()->format('Y-m-d') }}" required
                class="w-full px-3 py-2 rounded-lg border-gray-200 text-sm focus:border-brand-400 focus:ring-2 focus:ring-brand-500/15 transition">
            @error('date') <p class="text-xs text-red-600 mt-1">{{ $message }}</p> @enderror
        </div>

        <div class="grid grid-cols-2 gap-3">
            @foreach(['am_time_in' => 'AM In', 'am_time_out' => 'AM Out', 'pm_time_in' => 'PM In', 'pm_time_out' => 'PM Out', 'am_time_in_2' => 'AM In (2)', 'am_time_out_2' => 'AM Out (2)', 'pm_time_in_2' => 'PM In (2)', 'pm_time_out_2' => 'PM Out (2)'] as $field => $label)
                <div>
                    <label for="{{ $field }}" class="block text-xs font-medium text-gray-600 mb-1">{{ $label }}</label>
                    <input type="time" name="{{ $field }}" id="{{ $field }}" value="{{ old($field) }}"
                        class="w-full px-3 py-2 rounded-lg border-gray-200 text-sm focus:border-brand-400 focus:ring-2 focus:ring-brand-500/15 transition">
                </div>
            @endforeach
        </div>
        @error('am_time_in') <p class="text-xs text-red-600 -mt-2">{{ $message }}</p> @enderror

        <p class="text-[11px] text-gray-400">
            Enter at least one time — a lone slot (e.g. AM In) is fine for a half-open day. The (2) pair records a mid-session
            "stepped out and came back". Regular vs. overtime hours are auto-computed against the standard working hours.
        </p>

        <div>
            <label for="notes" class="block text-sm font-medium text-gray-700 mb-1.5">Notes</label>
            <textarea name="notes" id="notes" rows="3" placeholder="Why the scan was missed, context the admin should know…"
                class="w-full px-3 py-2 rounded-lg border-gray-200 text-sm placeholder:text-gray-300 focus:border-brand-400 focus:ring-2 focus:ring-brand-500/15 transition">{{ old('notes') }}</textarea>
        </div>

        <button type="submit" class="w-full bg-brand-600 hover:bg-brand-700 text-white text-sm font-medium py-2.5 rounded-lg transition">
            Save Pending Entry
        </button>
    </form>
</div>
@endsection
