@extends('layouts.app')

@section('title', 'Add Intern')

@section('content')
<div class="max-w-2xl mx-auto">
    <div class="mb-6">
        <h1 class="text-xl font-semibold tracking-tight text-gray-900">Add Intern</h1>
        <p class="text-sm text-gray-500 mt-0.5">Create an intern account and their first OJT set.</p>
    </div>

    <div class="bg-white border border-gray-200 rounded-xl p-6 sm:p-8">
        <form method="POST" action="{{ route('admin.interns.store') }}" class="space-y-5">
            @csrf

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1.5">First Name <span class="text-gray-400 font-normal text-xs">(include middle initial)</span></label>
                    <input type="text" name="first_name" value="{{ old('first_name') }}" required placeholder="Lady Pearl T"
                        class="w-full px-3 py-2 rounded-lg border-gray-200 text-sm placeholder:text-gray-300 focus:border-brand-400 focus:ring-2 focus:ring-brand-500/15 transition">
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1.5">Last Name</label>
                    <input type="text" name="last_name" value="{{ old('last_name') }}" required
                        class="w-full px-3 py-2 rounded-lg border-gray-200 text-sm placeholder:text-gray-300 focus:border-brand-400 focus:ring-2 focus:ring-brand-500/15 transition">
                </div>
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1.5">Student ID</label>
                    <input type="text" name="student_id" value="{{ old('student_id') }}" required
                        class="w-full px-3 py-2 rounded-lg border-gray-200 text-sm placeholder:text-gray-300 focus:border-brand-400 focus:ring-2 focus:ring-brand-500/15 transition">
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1.5">Department / Course</label>
                    <input type="text" name="department" value="{{ old('department') }}" placeholder="e.g. BSCS, BSINT"
                        class="w-full px-3 py-2 rounded-lg border-gray-200 text-sm placeholder:text-gray-300 focus:border-brand-400 focus:ring-2 focus:ring-brand-500/15 transition">
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1.5">Year Level</label>
                    <select name="year_level" class="w-full px-3 py-2 rounded-lg border-gray-200 text-sm focus:border-brand-400 focus:ring-2 focus:ring-brand-500/15 transition">
                        <option value="">—</option>
                        <option value="1" {{ old('year_level') == 1 ? 'selected' : '' }}>1st Year</option>
                        <option value="2" {{ old('year_level') == 2 ? 'selected' : '' }}>2nd Year</option>
                        <option value="3" {{ old('year_level') == 3 ? 'selected' : '' }}>3rd Year</option>
                        <option value="4" {{ old('year_level') == 4 ? 'selected' : '' }}>4th Year</option>
                    </select>
                </div>
            </div>

            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1.5">Email <span class="text-gray-400 font-normal text-xs">(optional — auto-generated if blank)</span></label>
                <input type="email" name="email" value="{{ old('email') }}"
                    class="w-full px-3 py-2 rounded-lg border-gray-200 text-sm placeholder:text-gray-300 focus:border-brand-400 focus:ring-2 focus:ring-brand-500/15 transition">
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1.5">OJT Track</label>
                    <select name="ojt_track" required onchange="
                        document.getElementById('customLabelField').classList.toggle('hidden', this.value !== 'custom');
                        document.getElementById('targetHoursField').value = this.value === 'internship' ? 500 : '';
                    " class="w-full px-3 py-2 rounded-lg border-gray-200 text-sm focus:border-brand-400 focus:ring-2 focus:ring-brand-500/15 transition">
                        <option value="internship" {{ old('ojt_track', 'internship') === 'internship' ? 'selected' : '' }}>Internship OJT (500h)</option>
                        <option value="custom" {{ old('ojt_track') === 'custom' ? 'selected' : '' }}>Custom</option>
                    </select>
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1.5">Target Hours</label>
                    <input type="number" id="targetHoursField" name="target_hours" required min="1" max="5000" value="{{ old('target_hours', 500) }}"
                        class="w-full px-3 py-2 rounded-lg border-gray-200 text-sm focus:border-brand-400 focus:ring-2 focus:ring-brand-500/15 transition">
                </div>
            </div>

            <div id="customLabelField" class="{{ old('ojt_track') === 'custom' ? '' : 'hidden' }}">
                <label class="block text-sm font-medium text-gray-700 mb-1.5">Custom Track Label</label>
                <input type="text" name="custom_label" value="{{ old('custom_label') }}" placeholder="e.g. Practicum OJT"
                    class="w-full px-3 py-2 rounded-lg border-gray-200 text-sm placeholder:text-gray-300 focus:border-brand-400 focus:ring-2 focus:ring-brand-500/15 transition">
            </div>

            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1.5">Training Starts On</label>
                <input type="date" name="training_starts_on" value="{{ old('training_starts_on', $defaultTrainingStart) }}"
                    class="w-full px-3 py-2 rounded-lg border-gray-200 text-sm focus:border-brand-400 focus:ring-2 focus:ring-brand-500/15 transition">
                <p class="text-xs text-gray-400 mt-1">Printed as the start month in this intern's application letter. Defaults to the OJT period start set in Settings.</p>
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1.5">OJT Status</label>
                    <select name="ojt_status" required class="w-full px-3 py-2 rounded-lg border-gray-200 text-sm focus:border-brand-400 focus:ring-2 focus:ring-brand-500/15 transition">
                        <option value="pending" {{ old('ojt_status', 'pending') === 'pending' ? 'selected' : '' }}>Pending</option>
                        <option value="active" {{ old('ojt_status') === 'active' ? 'selected' : '' }}>Active</option>
                        <option value="completed" {{ old('ojt_status') === 'completed' ? 'selected' : '' }}>Completed</option>
                    </select>
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1.5">Batch <span class="text-gray-400 font-normal text-xs">(optional)</span></label>
                    <input type="text" name="batch" value="{{ old('batch') }}" placeholder="e.g. 2026 Summer Batch A"
                        class="w-full px-3 py-2 rounded-lg border-gray-200 text-sm placeholder:text-gray-300 focus:border-brand-400 focus:ring-2 focus:ring-brand-500/15 transition">
                </div>
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1.5">Office Placement <span class="text-gray-400 font-normal text-xs">(optional)</span></label>
                    <select name="office_id" class="w-full px-3 py-2 rounded-lg border-gray-200 text-sm focus:border-brand-400 focus:ring-2 focus:ring-brand-500/15 transition">
                        <option value="">Not placed yet</option>
                        @foreach($offices as $office)
                            <option value="{{ $office->id }}" {{ old('office_id') == $office->id ? 'selected' : '' }}>
                                {{ $office->name }} ({{ $office->type_label }})
                            </option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1.5">OJT Coordinator <span class="text-gray-400 font-normal text-xs">(optional)</span></label>
                    <select name="coordinator_id" class="w-full px-3 py-2 rounded-lg border-gray-200 text-sm focus:border-brand-400 focus:ring-2 focus:ring-brand-500/15 transition">
                        <option value="">No coordinator</option>
                        @foreach($coordinators as $coordinator)
                            <option value="{{ $coordinator->id }}" {{ old('coordinator_id') == $coordinator->id ? 'selected' : '' }}>
                                {{ $coordinator->full_name }}
                            </option>
                        @endforeach
                    </select>
                </div>
            </div>

            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1.5">Password <span class="text-gray-400 font-normal text-xs">(optional — blank = their last name)</span></label>
                <input type="text" name="password" value="{{ old('password') }}" minlength="6"
                    placeholder="Leave blank to use their last name"
                    class="w-full px-3 py-2 rounded-lg border-gray-200 text-sm placeholder:text-gray-300 focus:border-brand-400 focus:ring-2 focus:ring-brand-500/15 transition">
                @error('password')
                    <p class="text-xs text-red-600 mt-1">{{ $message }}</p>
                @enderror
            </div>

            <div class="flex gap-2 pt-2">
                <button type="submit" class="bg-brand-600 hover:bg-brand-700 text-white text-sm font-medium px-4 py-2 rounded-lg transition">Create Intern</button>
                <a href="{{ route('admin.interns.index') }}" class="text-sm font-medium px-4 py-2 rounded-lg text-gray-500 hover:bg-gray-100 transition">Cancel</a>
            </div>
        </form>
    </div>
</div>
@endsection
