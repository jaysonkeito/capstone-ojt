@extends('layouts.app')

@section('title', $staff->exists ? 'Edit Staff' : 'Add Staff')

@section('content')
<div>
    <div class="mb-6">
        <a href="{{ route('admin.staff.index') }}" class="text-xs font-medium text-gray-400 hover:text-gray-700">← Staff</a>
        <h1 class="text-xl font-semibold tracking-tight text-gray-900 mt-1">{{ $staff->exists ? 'Edit '.$staff->full_name : 'Add Staff' }}</h1>
    </div>

    <div class="bg-white border border-gray-200 rounded-xl p-6 sm:p-8">
        <form method="POST" action="{{ $staff->exists ? route('admin.staff.update', $staff) : route('admin.staff.store') }}" class="space-y-5">
            @csrf
            @if($staff->exists) @method('PUT') @endif

            @php
                $selectedRoles = old('roles', $staff->exists ? $staff->allRoles() : ['coordinator']);
                $selectedPrimary = old('primary_role', $staff->role ?? 'coordinator');
                $roleDescriptions = [
                    'coordinator' => 'OJT Coordinator — monitors the interns assigned to them, plus their office\'s interns if one is set',
                    'supervisor' => 'Supervisor — monitors the interns at their office',
                    'dean' => 'College Dean — approves coordinator and supervisor sign-ups for their college',
                    'chair' => 'Program Chair — oversees their college\'s interns (one per program; CAS has two)',
                ];
            @endphp

            {{-- Roles — check every hat this account wears --}}
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1.5">Roles <span class="text-gray-400 font-normal text-xs">(check all that apply)</span></label>
                <div class="space-y-2">
                    @foreach($roleDescriptions as $roleValue => $roleDescription)
                        <label class="flex items-start gap-2.5 rounded-lg border {{ in_array($roleValue, $selectedRoles) ? 'border-brand-200 bg-brand-50/40' : 'border-gray-200' }} px-3.5 py-2.5 cursor-pointer hover:border-brand-300 transition">
                            <input type="checkbox" name="roles[]" value="{{ $roleValue }}" data-staff-role
                                {{ in_array($roleValue, $selectedRoles) ? 'checked' : '' }} onchange="syncStaffRoleFields()"
                                class="mt-0.5 rounded border-gray-300 text-brand-600 focus:ring-brand-500/20">
                            <span class="text-sm text-gray-700">{{ $roleDescription }}</span>
                        </label>
                    @endforeach
                </div>
                @error('roles') <p class="text-xs text-red-600 mt-1">{{ $message }}</p> @enderror
                @error('roles.0') <p class="text-xs text-red-600 mt-1">{{ $message }}</p> @enderror
            </div>

            {{-- Priority role — decides the dashboard, the display label, and where the account lands after login --}}
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1.5">Priority Role <span class="text-gray-400 font-normal text-xs">(the main hat — drives the dashboard and the account label)</span></label>
                <select name="primary_role" id="primaryRoleSelect" required
                    class="w-full px-3 py-2 rounded-lg border-gray-200 text-sm focus:border-brand-400 focus:ring-2 focus:ring-brand-500/15 transition">
                    @foreach($selectedRoles as $roleValue)
                        @if(in_array($roleValue, ['coordinator', 'supervisor', 'dean', 'chair']))
                            <option value="{{ $roleValue }}" {{ $selectedPrimary === $roleValue ? 'selected' : '' }}>
                                {{ ['coordinator' => 'OJT Coordinator', 'supervisor' => 'Supervisor', 'dean' => 'College Dean', 'chair' => 'Program Chair'][$roleValue] }}
                            </option>
                        @endif
                    @endforeach
                </select>
                @error('primary_role') <p class="text-xs text-red-600 mt-1">{{ $message }}</p> @enderror
            </div>

            {{-- College — editable on both create and edit so existing staff can be assigned --}}
            <div id="collegeField" class="mb-5">
                <label class="block text-sm font-medium text-gray-700 mb-1.5">College</label>
                <select name="college_code" class="w-full px-3 py-2 rounded-lg border-gray-200 text-sm focus:border-brand-400 focus:ring-2 focus:ring-brand-500/15 transition">
                    <option value="">— None —</option>
                    @foreach($colleges ?? [] as $college)
                        <option value="{{ $college->code }}" {{ old('college_code', $staff->staffProfile?->college_code ?? $staff->college_code) === $college->code ? 'selected' : '' }}>
                            {{ $college->name }}
                        </option>
                    @endforeach
                </select>
                @error('college_code') <p class="text-xs text-red-600 mt-1">{{ $message }}</p> @enderror
                <p class="text-[11px] text-gray-400 mt-1">Required for coordinators, deans, and Program Chairs. Supervisors of external offices may leave this blank — their sign-ups then go to the System Admin for approval.</p>
            </div>

            <div id="personFields">
            <div class="grid grid-cols-1 sm:grid-cols-4 gap-4">
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1.5">First Name</label>
                    <input type="text" name="first_name" value="{{ old('first_name', $staff->first_name) }}"
                        class="w-full px-3 py-2 rounded-lg border-gray-200 text-sm placeholder:text-gray-300 focus:border-brand-400 focus:ring-2 focus:ring-brand-500/15 transition">
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1.5">Middle Name</label>
                    <input type="text" name="middle_name" value="{{ old('middle_name', $staff->staffProfile?->middle_name) }}" placeholder="Full middle name"
                        class="w-full px-3 py-2 rounded-lg border-gray-200 text-sm placeholder:text-gray-300 focus:border-brand-400 focus:ring-2 focus:ring-brand-500/15 transition">
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1.5">Last Name</label>
                    <input type="text" name="last_name" value="{{ old('last_name', $staff->last_name) }}"
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

            <div id="officeField">
                <label class="block text-sm font-medium text-gray-700 mb-1.5">Office <span id="officeHint" class="text-gray-400 font-normal text-xs">(required for supervisors; optional for coordinators, deans, and Program Chairs — set it when they also supervise that office's interns and its desk scanner)</span></label>
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

@push('scripts')
<script>
    const staffRoleLabels = {
        coordinator: 'OJT Coordinator',
        supervisor: 'Supervisor',
        dean: 'College Dean',
        chair: 'Program Chair',
    };

    // Keep the priority-role select in step with the checked roles: only
    // checked roles are offered, unchecking the current priority moves it
    // to the first remaining role, and the office hint tightens when the
    // supervisor hat is checked.
    function syncStaffRoleFields() {
        const checked = [...document.querySelectorAll('input[data-staff-role]:checked')].map(input => input.value);

        const select = document.getElementById('primaryRoleSelect');
        const current = select.value;
        select.innerHTML = checked.map(role =>
            `<option value="${role}" ${role === current ? 'selected' : ''}>${staffRoleLabels[role]}</option>`
        ).join('');
        if (! checked.includes(current) && checked.length > 0) {
            select.value = checked[0];
        }

        const hint = document.getElementById('officeHint');
        if (checked.includes('supervisor') && checked.length === 1) {
            hint.textContent = '(required for supervisors — the desk station and intern list resolve to it)';
        } else {
            hint.textContent = "(required for supervisors; optional for coordinators, deans, and Program Chairs — set it when they also supervise that office's interns and its desk scanner)";
        }
    }
</script>
@endpush
@endsection
