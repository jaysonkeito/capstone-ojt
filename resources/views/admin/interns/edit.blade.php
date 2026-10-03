@extends('layouts.app')

@section('title', 'Edit Intern')

@section('content')
<div class="max-w-2xl mx-auto">
    <div class="mb-6">
        <h1 class="text-xl font-semibold tracking-tight text-gray-900">Edit Intern</h1>
        <p class="text-sm text-gray-500 mt-0.5">{{ $intern->full_name }} · {{ $intern->student_id }} ·
            <a href="{{ route('admin.interns.show', $intern) }}" class="text-brand-600 hover:underline">View full profile</a></p>
    </div>

    <div class="bg-white border border-gray-200 rounded-xl p-6 sm:p-8 space-y-8">
        <form method="POST" action="{{ route('admin.interns.update', $intern) }}" class="space-y-5">
            @csrf
            @method('PUT')

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1.5">First Name <span class="text-gray-400 font-normal text-xs">(include middle initial)</span></label>
                    <input type="text" name="first_name" value="{{ old('first_name', $intern->first_name) }}" required
                        class="w-full px-3 py-2 rounded-lg border-gray-200 text-sm focus:border-brand-400 focus:ring-2 focus:ring-brand-500/15 transition">
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1.5">Last Name</label>
                    <input type="text" name="last_name" value="{{ old('last_name', $intern->last_name) }}" required
                        class="w-full px-3 py-2 rounded-lg border-gray-200 text-sm focus:border-brand-400 focus:ring-2 focus:ring-brand-500/15 transition">
                </div>
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1.5">Student ID</label>
                    <input type="text" name="student_id" value="{{ old('student_id', $intern->student_id) }}" required
                        class="w-full px-3 py-2 rounded-lg border-gray-200 text-sm focus:border-brand-400 focus:ring-2 focus:ring-brand-500/15 transition">
                    @error('student_id') <p class="text-xs text-red-600 mt-1">{{ $message }}</p> @enderror
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1.5">Department / Course</label>
                    <input type="text" name="department" value="{{ old('department', $intern->department) }}" placeholder="e.g. BSCS, BSINT"
                        class="w-full px-3 py-2 rounded-lg border-gray-200 text-sm placeholder:text-gray-300 focus:border-brand-400 focus:ring-2 focus:ring-brand-500/15 transition">
                    @error('department') <p class="text-xs text-red-600 mt-1">{{ $message }}</p> @enderror
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1.5">Year Level</label>
                    <select name="year_level" class="w-full px-3 py-2 rounded-lg border-gray-200 text-sm focus:border-brand-400 focus:ring-2 focus:ring-brand-500/15 transition">
                        <option value="">—</option>
                        <option value="1" {{ old('year_level', $intern->year_level) == 1 ? 'selected' : '' }}>1st Year</option>
                        <option value="2" {{ old('year_level', $intern->year_level) == 2 ? 'selected' : '' }}>2nd Year</option>
                        <option value="3" {{ old('year_level', $intern->year_level) == 3 ? 'selected' : '' }}>3rd Year</option>
                        <option value="4" {{ old('year_level', $intern->year_level) == 4 ? 'selected' : '' }}>4th Year</option>
                    </select>
                    @error('year_level') <p class="text-xs text-red-600 mt-1">{{ $message }}</p> @enderror
                </div>
            </div>

            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1.5">Email</label>
                <input type="email" name="email" value="{{ old('email', $intern->email) }}" required
                    class="w-full px-3 py-2 rounded-lg border-gray-200 text-sm focus:border-brand-400 focus:ring-2 focus:ring-brand-500/15 transition">
                @error('email') <p class="text-xs text-red-600 mt-1">{{ $message }}</p> @enderror
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1.5">OJT Track</label>
                    <select name="ojt_track" required onchange="document.getElementById('customLabelFieldEdit').classList.toggle('hidden', this.value !== 'custom')"
                        class="w-full px-3 py-2 rounded-lg border-gray-200 text-sm focus:border-brand-400 focus:ring-2 focus:ring-brand-500/15 transition">
                        <option value="internship" {{ old('ojt_track', $intern->ojt_track) === 'internship' ? 'selected' : '' }}>Internship OJT</option>
                        <option value="custom" {{ old('ojt_track', $intern->ojt_track) === 'custom' ? 'selected' : '' }}>Custom</option>
                    </select>
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1.5">Target Hours</label>
                    <input type="number" name="target_hours" required min="1" max="5000" value="{{ old('target_hours', $intern->target_hours) }}"
                        class="w-full px-3 py-2 rounded-lg border-gray-200 text-sm focus:border-brand-400 focus:ring-2 focus:ring-brand-500/15 transition">
                </div>
            </div>

            <div id="customLabelFieldEdit" class="{{ old('ojt_track', $intern->ojt_track) === 'custom' ? '' : 'hidden' }}">
                <label class="block text-sm font-medium text-gray-700 mb-1.5">Custom Track Label</label>
                <input type="text" name="custom_label" value="{{ old('custom_label', $intern->currentEnrollment?->label) }}" placeholder="e.g. Practicum OJT"
                    class="w-full px-3 py-2 rounded-lg border-gray-200 text-sm placeholder:text-gray-300 focus:border-brand-400 focus:ring-2 focus:ring-brand-500/15 transition">
            </div>

            <p class="text-xs text-gray-400 leading-relaxed">
                Currently {{ number_format($intern->accumulated_hours, 1) }}/{{ $intern->target_hours }}h on "{{ $intern->ojt_track_label }}".
                This edits that same set in place — to start a completely new set with hours reset to zero, mark this one Completed first,
                then use <span class="font-medium text-gray-600">+ New Set</span> from the Interns list.
            </p>

            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1.5">Training Starts On</label>
                <input type="date" name="training_starts_on" value="{{ old('training_starts_on', $intern->currentEnrollment?->started_at?->format('Y-m-d')) }}"
                    class="w-full px-3 py-2 rounded-lg border-gray-200 text-sm focus:border-brand-400 focus:ring-2 focus:ring-brand-500/15 transition">
                <p class="text-xs text-gray-400 mt-1">Printed as the start month in this intern's application letter.</p>
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1.5">OJT Status</label>
                    <select name="ojt_status" required class="w-full px-3 py-2 rounded-lg border-gray-200 text-sm focus:border-brand-400 focus:ring-2 focus:ring-brand-500/15 transition">
                        <option value="pending" {{ old('ojt_status', $intern->ojt_status) === 'pending' ? 'selected' : '' }}>Pending</option>
                        <option value="active" {{ old('ojt_status', $intern->ojt_status) === 'active' ? 'selected' : '' }}>Active</option>
                        <option value="completed" {{ old('ojt_status', $intern->ojt_status) === 'completed' ? 'selected' : '' }}>Completed</option>
                    </select>
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1.5">Batch <span class="text-gray-400 font-normal text-xs">(optional)</span></label>
                    <input type="text" name="batch" value="{{ old('batch', $intern->batch) }}" placeholder="e.g. 2026 Summer Batch A"
                        class="w-full px-3 py-2 rounded-lg border-gray-200 text-sm placeholder:text-gray-300 focus:border-brand-400 focus:ring-2 focus:ring-brand-500/15 transition">
                </div>
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1.5">Office Placement</label>
                    <select name="office_id" class="w-full px-3 py-2 rounded-lg border-gray-200 text-sm focus:border-brand-400 focus:ring-2 focus:ring-brand-500/15 transition">
                        <option value="">Not placed</option>
                        @foreach($offices as $office)
                            <option value="{{ $office->id }}" {{ old('office_id', $intern->office_id) == $office->id ? 'selected' : '' }}>
                                {{ $office->name }} ({{ $office->type_label }})
                            </option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1.5">OJT Coordinator</label>
                    <select name="coordinator_id" class="w-full px-3 py-2 rounded-lg border-gray-200 text-sm focus:border-brand-400 focus:ring-2 focus:ring-brand-500/15 transition">
                        <option value="">No coordinator</option>
                        @foreach($coordinators as $coordinator)
                            <option value="{{ $coordinator->id }}" {{ old('coordinator_id', $intern->coordinator_id) == $coordinator->id ? 'selected' : '' }}>
                                {{ $coordinator->full_name }}
                            </option>
                        @endforeach
                    </select>
                </div>
            </div>

            <label class="flex items-center gap-2 text-sm text-gray-600">
                <input type="hidden" name="is_active" value="0">
                <input type="checkbox" name="is_active" value="1" {{ old('is_active', $intern->is_active) ? 'checked' : '' }}
                    class="rounded border-gray-300 text-brand-600 focus:ring-brand-500/20">
                Account active (can log in)
            </label>

            <div class="flex gap-2 pt-2 flex-wrap">
                <button type="submit" class="bg-brand-600 hover:bg-brand-700 text-white text-sm font-medium px-4 py-2 rounded-lg transition">Save Changes</button>
                <a href="{{ route('admin.interns.index') }}" class="text-sm font-medium px-4 py-2 rounded-lg text-gray-500 hover:bg-gray-100 transition">Cancel</a>
                <a href="{{ route('admin.interns.timesheet', $intern) }}" target="_blank" class="text-sm font-medium px-4 py-2 rounded-lg text-brand-600 hover:bg-brand-50 border border-brand-200 transition">Time Frame</a>
                <a href="{{ route('admin.interns.summary', $intern) }}" target="_blank" class="text-sm font-medium px-4 py-2 rounded-lg text-brand-600 hover:bg-brand-50 border border-brand-200 transition">Monthly Summary</a>
            </div>
        </form>

        <div class="pt-5 border-t border-gray-100">
            <form method="POST" action="{{ route('admin.interns.reset-password', $intern) }}"
                  data-confirm-title="Reset password"
                  data-confirm-message="Reset {{ $intern->display_name }}'s password back to their last name ({{ $intern->last_name }})? They'll use it to sign in until they change it."
                  data-confirm-action="Reset"
                  onsubmit="return askConfirm(this);">
                @csrf
                <button type="submit" class="text-sm font-medium text-red-600 hover:underline">Reset password to last name</button>
            </form>
        </div>
    </div>
</div>

@include('partials.confirm-modal')
@endsection
