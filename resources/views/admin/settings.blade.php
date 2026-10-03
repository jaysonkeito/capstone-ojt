@extends('layouts.app')

@section('title', 'Settings')

@section('content')
<div class="max-w-3xl">
    <div class="mb-8">
        <h1 class="text-xl font-semibold tracking-tight text-gray-900">Settings</h1>
        <p class="text-sm text-gray-500 mt-0.5">Attendance rules that apply campus-wide and drive automatic overtime detection.</p>
    </div>

    <form method="POST" action="{{ route('admin.settings.update') }}" class="space-y-8">
        @csrf
        @method('PUT')

        {{-- Standard Working Hours --}}
        <section class="bg-white border border-gray-200 rounded-xl p-6 sm:p-7">
            <h2 class="text-sm font-semibold text-gray-900 mb-5">Standard Working Hours</h2>

            <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
                <div>
                    <label class="block text-xs font-medium text-gray-500 mb-1.5">AM Time In</label>
                    <input type="time" name="am_time_in" value="{{ old('am_time_in', substr($settings->am_time_in, 0, 5)) }}" required
                        class="w-full px-3 py-2 rounded-lg border-gray-200 text-sm focus:border-brand-400 focus:ring-2 focus:ring-brand-500/15 transition">
                </div>
                <div>
                    <label class="block text-xs font-medium text-gray-500 mb-1.5">AM Time Out</label>
                    <input type="time" name="am_time_out" value="{{ old('am_time_out', substr($settings->am_time_out, 0, 5)) }}" required
                        class="w-full px-3 py-2 rounded-lg border-gray-200 text-sm focus:border-brand-400 focus:ring-2 focus:ring-brand-500/15 transition">
                </div>
                <div>
                    <label class="block text-xs font-medium text-gray-500 mb-1.5">PM Time In</label>
                    <input type="time" name="pm_time_in" value="{{ old('pm_time_in', substr($settings->pm_time_in, 0, 5)) }}" required
                        class="w-full px-3 py-2 rounded-lg border-gray-200 text-sm focus:border-brand-400 focus:ring-2 focus:ring-brand-500/15 transition">
                </div>
                <div>
                    <label class="block text-xs font-medium text-gray-500 mb-1.5">PM Time Out</label>
                    <input type="time" name="pm_time_out" value="{{ old('pm_time_out', substr($settings->pm_time_out, 0, 5)) }}" required
                        class="w-full px-3 py-2 rounded-lg border-gray-200 text-sm focus:border-brand-400 focus:ring-2 focus:ring-brand-500/15 transition">
                    <p class="text-[11px] text-gray-400 mt-1">Clock-outs past this time auto-count as overtime.</p>
                </div>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mt-5">
                <div>
                    <label class="block text-xs font-medium text-gray-500 mb-1.5">Grace Period (minutes)</label>
                    <input type="number" name="grace_period_minutes" min="0" max="120" value="{{ old('grace_period_minutes', $settings->grace_period_minutes) }}" required
                        class="w-full px-3 py-2 rounded-lg border-gray-200 text-sm focus:border-brand-400 focus:ring-2 focus:ring-brand-500/15 transition">
                    <p class="text-[11px] text-gray-400 mt-1">Arrivals within this many minutes after AM Time In won't be marked late.</p>
                </div>
                <div>
                    <label class="block text-xs font-medium text-gray-500 mb-1.5">Absence Start Date</label>
                    <input type="date" name="absence_start_date" value="{{ old('absence_start_date', $settings->absence_start_date?->format('Y-m-d')) }}"
                        class="w-full px-3 py-2 rounded-lg border-gray-200 text-sm focus:border-brand-400 focus:ring-2 focus:ring-brand-500/15 transition">
                    <p class="text-[11px] text-gray-400 mt-1">Absences are only counted from this date onward; hours still count for earlier dates.</p>
                </div>
            </div>
        </section>

        {{-- OJT Training Period --}}
        <section class="bg-white border border-gray-200 rounded-xl p-6 sm:p-7">
            <h2 class="text-sm font-semibold text-gray-900 mb-5">OJT Training Period</h2>

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-5">
                <div>
                    <label class="block text-xs font-medium text-gray-500 mb-1.5">Default Training Start</label>
                    <input type="date" name="training_starts_on" value="{{ old('training_starts_on', $settings->training_starts_on?->format('Y-m-d')) }}"
                        class="w-full px-3 py-2 rounded-lg border-gray-200 text-sm focus:border-brand-400 focus:ring-2 focus:ring-brand-500/15 transition">
                    <p class="text-[11px] text-gray-400 mt-1">Pre-fills new interns' records and prints as the start month in application letters. Each intern can override theirs.</p>
                </div>
                <div>
                    <label class="block text-xs font-medium text-gray-500 mb-1.5">College Name</label>
                    <input type="text" name="college_name" value="{{ old('college_name', $settings->college_name) }}" maxlength="120"
                        class="w-full px-3 py-2 rounded-lg border-gray-200 text-sm focus:border-brand-400 focus:ring-2 focus:ring-brand-500/15 transition">
                    <p class="text-[11px] text-gray-400 mt-1">Printed on the Training Agreement, In-plant Agreements, Endorsement Letter, and Weekly Progress Report.</p>
                </div>
                <div>
                    <label class="block text-xs font-medium text-gray-500 mb-1.5">College Dean</label>
                    <input type="text" name="college_dean" value="{{ old('college_dean', $settings->college_dean) }}" maxlength="120"
                        class="w-full px-3 py-2 rounded-lg border-gray-200 text-sm focus:border-brand-400 focus:ring-2 focus:ring-brand-500/15 transition">
                    <p class="text-[11px] text-gray-400 mt-1">Signs the Endorsement Letter and the Personal Information sheet as College Dean. Update here — no document editing needed.</p>
                </div>
                <div>
                    <label class="block text-xs font-medium text-gray-500 mb-1.5">College Code</label>
                    <input type="text" name="college_code" value="{{ old('college_code', $settings->college_code) }}" maxlength="20" pattern="[a-z0-9_-]+"
                        class="w-full px-3 py-2 rounded-lg border-gray-200 text-sm focus:border-brand-400 focus:ring-2 focus:ring-brand-500/15 transition">
                    <p class="text-[11px] text-gray-400 mt-1">Lowercase folder code (e.g. cas, cba) — scopes which template folder serves the forms. Changing it after templates exist requires moving those folders to match.</p>
                </div>
            </div>
        </section>

        {{-- Working Days --}}
        <section class="bg-white border border-gray-200 rounded-xl p-6 sm:p-7">
            <h2 class="text-sm font-semibold text-gray-900 mb-5">Working Days</h2>

            @php
                $dayLabels = ['Sunday','Monday','Tuesday','Wednesday','Thursday','Friday','Saturday'];
                $activeDays = old('working_days', $settings->working_days ?? []);
            @endphp

            <div class="grid grid-cols-2 sm:grid-cols-4 md:grid-cols-7 gap-2">
                @foreach($dayLabels as $num => $label)
                    @php $isOn = in_array($num, $activeDays); @endphp
                    <label class="working-day-toggle cursor-pointer rounded-lg border px-3 py-3 flex flex-col items-center gap-2 transition
                        {{ $isOn ? 'border-brand-200 bg-brand-50' : 'border-gray-200 bg-white hover:bg-gray-50' }}"
                        data-on="{{ $isOn ? '1' : '0' }}">
                        <span class="day-name text-sm {{ $isOn ? 'text-brand-700 font-medium' : 'text-gray-500' }}">{{ $label }}</span>
                        <input type="checkbox" name="working_days[]" value="{{ $num }}" {{ $isOn ? 'checked' : '' }} class="sr-only">
                        <span class="day-track w-8 h-[18px] rounded-full relative transition {{ $isOn ? 'bg-brand-500' : 'bg-gray-200' }}">
                            <span class="day-dot absolute top-[2px] {{ $isOn ? 'right-[2px]' : 'left-[2px]' }} w-[14px] h-[14px] rounded-full bg-white shadow transition"></span>
                        </span>
                    </label>
                @endforeach
            </div>

            <button type="submit" class="mt-7 inline-flex items-center gap-1.5 bg-brand-600 hover:bg-brand-700 text-white text-sm font-medium px-4 py-2 rounded-lg transition">
                Save Settings
            </button>
        </section>
    </form>

    {{-- No-Class Calendar --}}
    <section class="bg-white border border-gray-200 rounded-xl p-6 sm:p-7 mt-8">
        <div class="flex flex-wrap items-center justify-between gap-3 mb-5">
            <div>
                <h2 class="text-sm font-semibold text-gray-900">No-Class Calendar</h2>
                <p class="text-xs text-gray-400 mt-0.5">{{ $noClassCount }} no-class day{{ $noClassCount === 1 ? '' : 's' }} this month</p>
            </div>
            <div class="flex items-center gap-2">
                <a href="{{ route('admin.settings.edit', ['month' => $prevMonth]) }}" class="w-8 h-8 rounded-lg border border-gray-200 flex items-center justify-center hover:bg-gray-50 text-gray-500 transition">&larr;</a>
                <span class="font-medium text-gray-700 w-32 text-center text-sm">{{ $month->format('F Y') }}</span>
                <a href="{{ route('admin.settings.edit', ['month' => $nextMonth]) }}" class="w-8 h-8 rounded-lg border border-gray-200 flex items-center justify-center hover:bg-gray-50 text-gray-500 transition">&rarr;</a>
            </div>
        </div>

        <div class="grid grid-cols-7 gap-1.5 text-center text-[11px] font-medium uppercase tracking-wider text-gray-400 mb-2">
            <div>Sun</div><div>Mon</div><div>Tue</div><div>Wed</div><div>Thu</div><div>Fri</div><div>Sat</div>
        </div>

        @foreach($weeks as $week)
            <div class="grid grid-cols-7 gap-1.5 mb-1.5">
                @foreach($week as $day)
                    <form method="POST" action="{{ route('admin.no-class-days.store') }}">
                        @csrf
                        <input type="hidden" name="date" value="{{ $day['date']->format('Y-m-d') }}">
                        <button type="submit" {{ $day['in_month'] ? '' : 'disabled' }}
                            class="w-full aspect-square rounded-lg text-sm tabular-nums transition
                            {{ !$day['in_month'] ? 'text-gray-200 cursor-default' : ($day['no_class'] ? 'bg-red-50 text-red-600 border border-red-200 hover:bg-red-100' : 'bg-gray-50 text-gray-600 hover:bg-gray-100 border border-transparent') }}">
                            {{ $day['date']->day }}
                        </button>
                    </form>
                @endforeach
            </div>
        @endforeach

        <p class="text-xs text-gray-400 mt-3">Click a date to mark/unmark it as a no-class day (holidays, suspensions). Interns won't be expected to log hours on these dates.</p>
    </section>
</div>

@push('scripts')
<script>
document.querySelectorAll('.working-day-toggle').forEach(function (label) {
    label.addEventListener('click', function () {
        const checkbox = label.querySelector('input[type="checkbox"]');
        const isOn = checkbox.checked; // pre-toggle state (browser toggles it right after this handler)
        const nowOn = !isOn;

        label.classList.toggle('border-brand-200', nowOn);
        label.classList.toggle('bg-brand-50', nowOn);
        label.classList.toggle('border-gray-200', !nowOn);
        label.classList.toggle('bg-white', !nowOn);

        const name = label.querySelector('.day-name');
        name.classList.toggle('text-brand-700', nowOn);
        name.classList.toggle('font-medium', nowOn);
        name.classList.toggle('text-gray-500', !nowOn);

        const track = label.querySelector('.day-track');
        track.classList.toggle('bg-brand-500', nowOn);
        track.classList.toggle('bg-gray-200', !nowOn);

        const dot = label.querySelector('.day-dot');
        dot.classList.toggle('right-[2px]', nowOn);
        dot.classList.toggle('left-[2px]', !nowOn);
    });
});
</script>
@endpush
@endsection
