@extends('layouts.app')

@section('title', $staff->exists ? 'Edit Staff' : 'Add Staff')

@section('content')
<div>
    <div class="mb-6">
        <a href="{{ route('admin.staff.index') }}" class="text-xs font-medium text-gray-400 hover:text-gray-700">← Staff</a>
        <h1 class="text-xl font-semibold tracking-tight text-gray-900 mt-1">{{ $staff->exists ? 'Edit '.$staff->role_label : 'Add Staff' }}</h1>
    </div>

    <div class="bg-white border border-gray-200 rounded-xl p-6 sm:p-8">
        <form method="POST" action="{{ $staff->exists ? route('admin.staff.update', $staff) : route('admin.staff.store') }}" class="space-y-5">
            @csrf
            @if($staff->exists) @method('PUT') @endif

            @unless($staff->exists)
                <div class="mb-5">
                    <label class="block text-sm font-medium text-gray-700 mb-1.5">Role</label>
                    <select name="role" required onchange="document.getElementById('officeField').classList.toggle('hidden', this.value !== 'supervisor')"
                        class="w-full px-3 py-2 rounded-lg border-gray-200 text-sm focus:border-brand-400 focus:ring-2 focus:ring-brand-500/15 transition">
                        <option value="coordinator" {{ old('role', $staff->role) === 'coordinator' ? 'selected' : '' }}>OJT Coordinator — monitors the interns assigned to them</option>
                        <option value="supervisor" {{ old('role', $staff->role) === 'supervisor' ? 'selected' : '' }}>Supervisor — monitors the interns at their office</option>
                        <option value="dean" {{ old('role', $staff->role) === 'dean' ? 'selected' : '' }}>College Dean — approves coordinator and supervisor sign-ups for their college</option>
                    </select>
                </div>
            @endunless

            {{-- College — editable on both create and edit so existing staff can be assigned --}}
            <div class="mb-5">
                <label class="block text-sm font-medium text-gray-700 mb-1.5">College</label>
                <select name="college_code" required class="w-full px-3 py-2 rounded-lg border-gray-200 text-sm focus:border-brand-400 focus:ring-2 focus:ring-brand-500/15 transition">
                    @foreach($colleges ?? [] as $college)
                        <option value="{{ $college->code }}" {{ old('college_code', $staff->staffProfile?->college_code ?? $staff->college_code ?? 'cas') === $college->code ? 'selected' : '' }}>
                            {{ $college->name }}
                        </option>
                    @endforeach
                </select>
                <p class="text-[11px] text-gray-400 mt-1">Scopes approvals and which college's document templates this staff member manages.</p>
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-4 gap-4">
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1.5">First Name</label>
                    <input type="text" name="first_name" value="{{ old('first_name', $staff->first_name) }}" required
                        class="w-full px-3 py-2 rounded-lg border-gray-200 text-sm placeholder:text-gray-300 focus:border-brand-400 focus:ring-2 focus:ring-brand-500/15 transition">
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1.5">Middle Name</label>
                    <input type="text" name="middle_name" value="{{ old('middle_name', $staff->staffProfile?->middle_name) }}" placeholder="Full middle name"
                        class="w-full px-3 py-2 rounded-lg border-gray-200 text-sm placeholder:text-gray-300 focus:border-brand-400 focus:ring-2 focus:ring-brand-500/15 transition">
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1.5">Last Name</label>
                    <input type="text" name="last_name" value="{{ old('last_name', $staff->last_name) }}" required
                        class="w-full px-3 py-2 rounded-lg border-gray-200 text-sm placeholder:text-gray-300 focus:border-brand-400 focus:ring-2 focus:ring-brand-500/15 transition">
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1.5">Title <span class="text-gray-400 font-normal text-xs">(e.g. Ph.D, LPT)</span></label>
                    <input type="text" name="title" value="{{ old('title', $staff->title) }}" placeholder="e.g. Ph.D, LPT"
                        class="w-full px-3 py-2 rounded-lg border-gray-200 text-sm placeholder:text-gray-300 focus:border-brand-400 focus:ring-2 focus:ring-brand-500/15 transition">
                </div>
            </div>

            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1.5">Position <span class="text-gray-400 font-normal text-xs">(e.g. MIS, Campus Director)</span></label>
                <input type="text" name="position" value="{{ old('position', $staff->position) }}" placeholder="e.g. MIS, Campus Director"
                    class="w-full px-3 py-2 rounded-lg border-gray-200 text-sm placeholder:text-gray-300 focus:border-brand-400 focus:ring-2 focus:ring-brand-500/15 transition">
            </div>

            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1.5">Email <span class="text-gray-400 font-normal text-xs">(used to log in)</span></label>
                <input type="email" name="email" value="{{ old('email', $staff->email) }}" required
                    class="w-full px-3 py-2 rounded-lg border-gray-200 text-sm placeholder:text-gray-300 focus:border-brand-400 focus:ring-2 focus:ring-brand-500/15 transition">
                @error('email') <p class="text-xs text-red-600 mt-1">{{ $message }}</p> @enderror
            </div>

            @if(! $staff->exists)
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1.5">Password</label>
                    <input type="text" name="password" value="{{ old('password') }}" required minlength="8"
                        placeholder="At least 8 characters — share it with the staff member"
                        class="w-full px-3 py-2 rounded-lg border-gray-200 text-sm placeholder:text-gray-300 focus:border-brand-400 focus:ring-2 focus:ring-brand-500/15 transition">
                    @error('password') <p class="text-xs text-red-600 mt-1">{{ $message }}</p> @enderror
                </div>
            @endif

            <div id="officeField" class="{{ ($staff->exists ? $staff->role !== 'supervisor' : old('role', $staff->role) !== 'supervisor') ? 'hidden' : '' }}">
                <label class="block text-sm font-medium text-gray-700 mb-1.5">Office <span class="text-gray-400 font-normal text-xs">(required for supervisors)</span></label>
                <select name="office_id" class="w-full px-3 py-2 rounded-lg border-gray-200 text-sm focus:border-brand-400 focus:ring-2 focus:ring-brand-500/15 transition">
                    <option value="">Select office</option>
                    @foreach($offices as $office)
                        <option value="{{ $office->id }}" {{ old('office_id', $staff->office_id) == $office->id ? 'selected' : '' }}>
                            {{ $office->name }} ({{ $office->type_label }})
                        </option>
                    @endforeach
                </select>
                @error('office_id') <p class="text-xs text-red-600 mt-1">{{ $message }}</p> @enderror
            </div>

            <label class="flex items-center gap-2 text-sm text-gray-600">
                <input type="hidden" name="is_active" value="0">
                <input type="checkbox" name="is_active" value="1" {{ old('is_active', $staff->exists ? $staff->is_active : true) ? 'checked' : '' }}
                    class="rounded border-gray-300 text-brand-600 focus:ring-brand-500/20">
                Account active (can log in)
            </label>

            <div class="flex gap-2 pt-2">
                <button type="submit" class="bg-brand-600 hover:bg-brand-700 text-white text-sm font-medium px-4 py-2 rounded-lg transition">{{ $staff->exists ? 'Save Changes' : 'Create Account' }}</button>
                <a href="{{ route('admin.staff.index') }}" class="text-sm font-medium px-4 py-2 rounded-lg text-gray-500 hover:bg-gray-100 transition">Cancel</a>
            </div>
        </form>
    </div>
</div>
@endsection
