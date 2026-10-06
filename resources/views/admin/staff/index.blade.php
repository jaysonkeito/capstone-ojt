@extends('layouts.app')

@section('title', 'Staff')

@section('content')
<div class="flex flex-wrap items-center justify-between gap-3 mb-6">
    <div>
        <h1 class="text-xl font-semibold tracking-tight text-gray-900">Staff</h1>
        <p class="text-sm text-gray-500 mt-0.5">OJT Coordinators and office Supervisors — monitoring accounts.</p>
    </div>
    <button type="button" onclick="document.getElementById('addStaffModal').classList.remove('hidden')"
        class="inline-flex items-center gap-1.5 bg-brand-600 hover:bg-brand-700 text-white text-sm font-medium px-3.5 py-2 rounded-lg transition">+ Add Staff</button>
</div>

{{-- Role filter --}}
<form method="GET" class="mb-5 flex items-center gap-2">
    <select name="role" onchange="this.form.submit()" class="px-3 py-2 rounded-lg border-gray-200 text-sm text-gray-600 focus:border-brand-400 focus:ring-2 focus:ring-brand-500/15 transition">
        <option value="">All Staff</option>
        <option value="coordinator" {{ request('role') === 'coordinator' ? 'selected' : '' }}>OJT Coordinators</option>
        <option value="supervisor" {{ request('role') === 'supervisor' ? 'selected' : '' }}>Supervisors</option>
        <option value="dean" {{ request('role') === 'dean' ? 'selected' : '' }}>College Deans</option>
        <option value="office" {{ request('role') === 'office' ? 'selected' : '' }}>Office Scanners</option>
    </select>
    @if(request('role'))
        <a href="{{ route('admin.staff.index') }}" class="text-sm font-medium text-gray-400 hover:text-gray-700 px-2 py-2">Clear</a>
    @endif
</form>

<div class="bg-white border border-gray-200 rounded-xl overflow-x-auto">
    <table class="w-full text-sm">
        <thead>
            <tr class="text-left text-[11px] font-medium uppercase tracking-widest text-gray-400 border-b border-gray-200">
                <th class="px-5 py-3">Name</th>
                <th class="px-4 py-3">Role</th>
                <th class="px-4 py-3">Office</th>
                <th class="px-4 py-3 text-center">Interns</th>
                <th class="px-4 py-3">Status</th>
                <th class="px-5 py-3 text-right">Actions</th>
            </tr>
        </thead>
        <tbody class="divide-y divide-gray-100">
            @forelse($staff as $member)
                <tr class="hover:bg-gray-50/60 transition-colors">
                    <td class="px-5 py-3">
                        <div class="flex items-center gap-2.5">
                            @include('partials.avatar', ['user' => $member, 'class' => 'w-8 h-8 text-[10px]'])
                            <div class="min-w-0">
                                <p class="font-medium text-gray-900">{{ $member->display_name_with_middle_initial }}@if($member->title) <span class="text-xs font-normal text-gray-400">{{ $member->title }}</span>@endif</p>
                                @if($member->position) <p class="text-xs text-gray-500">{{ $member->position }}</p> @endif
                                <p class="text-xs text-gray-400">{{ $member->email }}</p>
                            </div>
                        </div>
                    </td>
                    <td class="px-4 py-3">
                        <span class="inline-flex items-center px-2 py-0.5 rounded-md text-xs font-medium {{ $member->isCoordinator() ? 'bg-brand-50 text-brand-700' : 'bg-gray-100 text-gray-600' }}">
                            {{ $member->role_label }}
                        </span>
                    </td>
                    <td class="px-4 py-3 text-gray-500">{{ $member->office?->name ?? '—' }}</td>
                    <td class="px-4 py-3 text-center text-gray-700 tabular-nums">{{ $member->isCoordinator() ? $member->coordinated_interns_count : $member->office?->interns()->count() }}</td>
                    <td class="px-4 py-3">
                        <span class="inline-flex items-center gap-1.5 text-xs font-medium {{ $member->is_active ? 'text-emerald-700' : 'text-gray-400' }}">
                            <span class="w-1.5 h-1.5 rounded-full {{ $member->is_active ? 'bg-emerald-500' : 'bg-gray-300' }}"></span>
                            {{ $member->is_active ? 'Active' : 'Inactive' }}
                        </span>
                    </td>
                    <td class="px-5 py-3 text-right whitespace-nowrap">
                        <a href="{{ route('admin.staff.edit', $member) }}" class="text-xs font-medium text-brand-600 hover:underline">Edit</a>
                        <form method="POST" action="{{ route('admin.staff.destroy', $member) }}" class="inline"
                              data-confirm-title="Delete staff account"
                              data-confirm-message="Delete {{ $member->display_name_with_middle_initial }}? They can no longer log in — you can restore them anytime from the Deleted section below."
                              data-confirm-action="Delete"
                              onsubmit="return askConfirm(this);">
                            @csrf
                            @method('DELETE')
                            <button class="text-xs font-medium text-red-500 hover:underline ml-3">Delete</button>
                        </form>
                    </td>
                </tr>
            @empty
                <tr><td colspan="6" class="px-5 py-10 text-center text-gray-400 text-sm">No staff accounts yet.</td></tr>
            @endforelse
        </tbody>
    </table>
</div>

<div class="mt-4">
    {{ $staff->links() }}
</div>

{{-- Soft-deleted staff, restorable --}}
@php
    $deletedStaff = App\Models\User::onlyTrashed()
        ->whereIn('role', ['coordinator', 'supervisor'])
        ->when(request('role'), fn ($q, $role) => $q->where('role', $role))
        ->orderBy('last_name')
        ->get();
@endphp
@if($deletedStaff->isNotEmpty())
    <div class="mt-8 bg-white border border-gray-200 rounded-xl overflow-x-auto">
        <div class="px-5 py-3.5 border-b border-gray-100 flex items-center justify-between">
            <h2 class="text-sm font-semibold text-gray-900">Deleted <span class="text-gray-400 font-normal">({{ $deletedStaff->count() }})</span></h2>
            <p class="text-xs text-gray-400">Hidden from the system and can't log in — restoring brings back their assignments.</p>
        </div>
        <table class="w-full text-sm">
            <tbody class="divide-y divide-gray-100">
                @foreach($deletedStaff as $member)
                    <tr class="hover:bg-gray-50/60 transition-colors">
                        <td class="px-5 py-3">
                            <p class="font-medium text-gray-400 line-through">{{ $member->display_name_with_middle_initial }}</p>
                            <p class="text-xs text-gray-300">{{ $member->email }}</p>
                        </td>
                        <td class="px-4 py-3 text-gray-400">{{ $member->role_label }}@if($member->position)<span class="block text-xs text-gray-300">{{ $member->position }}</span>@endif</td>
                        <td class="px-4 py-3 text-gray-400">{{ $member->office?->name ?? '—' }}</td>
                        <td class="px-4 py-3 text-xs text-gray-400">deleted {{ $member->deleted_at?->diffForHumans() }}</td>
                        <td class="px-5 py-3 text-right whitespace-nowrap">
                            <form method="POST" action="{{ route('admin.staff.restore', $member->id) }}" class="inline">
                                @csrf
                                <button class="text-xs font-medium text-brand-600 hover:underline">Restore</button>
                            </form>
                            <form method="POST" action="{{ route('admin.staff.force-delete', $member->id) }}" class="inline"
                                  data-confirm-title="Delete permanently"
                                  data-confirm-message="Permanently delete {{ $member->display_name_with_middle_initial }}? This cannot be undone — intern records are kept, but this account is gone for good."
                                  data-confirm-action="Delete permanently"
                                  onsubmit="return askConfirm(this);">
                                @csrf
                                @method('DELETE')
                                <button class="text-xs font-medium text-red-500 hover:underline ml-4">Delete permanently</button>
                            </form>
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>
@endif

{{-- Add Staff Modal --}}
<div id="addStaffModal" class="hidden fixed inset-0 z-50 flex items-start justify-center bg-gray-900/25 px-4 pt-[5vh] overflow-y-auto"
     onclick="if(event.target === this) this.classList.add('hidden')">
    <div class="bg-white border border-gray-200 rounded-xl shadow-xl w-full max-w-md p-6 mb-10">
        <div class="flex items-center justify-between mb-5">
            <h3 class="font-semibold text-gray-900">Add Staff</h3>
            <button type="button" onclick="document.getElementById('addStaffModal').classList.add('hidden')" class="text-gray-400 hover:text-gray-900">
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M18 6 6 18M6 6l12 12"/></svg>
            </button>
        </div>
        <form method="POST" action="{{ route('admin.staff.store') }}" class="space-y-4">
            @csrf
            <div>
                <label class="block text-xs font-medium text-gray-600 mb-1">Role</label>
                <select name="role" required onchange="syncModalStaffRoleFields(this.value)"
                    class="w-full px-3 py-2 rounded-lg border-gray-200 text-sm focus:border-brand-400 focus:ring-2 focus:ring-brand-500/15 transition">
                    <option value="coordinator">OJT Coordinator — monitors the interns assigned to them</option>
                    <option value="supervisor">Supervisor — monitors the interns at their office</option>
                    <option value="dean">College Dean — approves coordinator and supervisor sign-ups for their college</option>
                    <option value="office">Office Scanner — scanner-only account for the kiosk PC; nothing else</option>
                </select>
            </div>
            <div id="modalCollegeField">
                <label class="block text-xs font-medium text-gray-600 mb-1">College <span class="text-gray-400 font-normal">(optional for supervisors)</span></label>
                <select name="college_code" class="w-full px-3 py-2 rounded-lg border-gray-200 text-sm focus:border-brand-400 focus:ring-2 focus:ring-brand-500/15 transition">
                    <option value="">— None —</option>
                    @foreach($colleges as $college)
                        <option value="{{ $college->code }}" {{ old('college_code') === $college->code ? 'selected' : '' }}>{{ $college->name }}</option>
                    @endforeach
                </select>
            </div>
            <div id="modalPersonFields">
            <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
                <div>
                    <label class="block text-xs font-medium text-gray-600 mb-1">First Name</label>
                    <input type="text" name="first_name" value="{{ old('first_name') }}" required
                        class="w-full px-3 py-2 rounded-lg border-gray-200 text-sm placeholder:text-gray-300 focus:border-brand-400 focus:ring-2 focus:ring-brand-500/15 transition">
                </div>
                <div>
                    <label class="block text-xs font-medium text-gray-600 mb-1">Middle Name</label>
                    <input type="text" name="middle_name" value="{{ old('middle_name') }}" placeholder="Full middle name"
                        class="w-full px-3 py-2 rounded-lg border-gray-200 text-sm placeholder:text-gray-300 focus:border-brand-400 focus:ring-2 focus:ring-brand-500/15 transition">
                </div>
                <div>
                    <label class="block text-xs font-medium text-gray-600 mb-1">Last Name</label>
                    <input type="text" name="last_name" value="{{ old('last_name') }}" required
                        class="w-full px-3 py-2 rounded-lg border-gray-200 text-sm placeholder:text-gray-300 focus:border-brand-400 focus:ring-2 focus:ring-brand-500/15 transition">
                </div>
            </div>
            <div>
                <label class="block text-xs font-medium text-gray-600 mb-1">Title <span class="text-gray-400 font-normal">(e.g. Ph.D, LPT, MSIT)</span></label>
                <input type="text" name="title" value="{{ old('title') }}" placeholder="e.g. Ph.D, LPT"
                    class="w-full px-3 py-2 rounded-lg border-gray-200 text-sm placeholder:text-gray-300 focus:border-brand-400 focus:ring-2 focus:ring-brand-500/15 transition">
            </div>
            <div>
                <label class="block text-xs font-medium text-gray-600 mb-1">Position <span class="text-gray-400 font-normal">(e.g. MIS, Campus Director)</span></label>
                <input type="text" name="position" value="{{ old('position') }}" placeholder="e.g. MIS, Campus Director"
                    class="w-full px-3 py-2 rounded-lg border-gray-200 text-sm placeholder:text-gray-300 focus:border-brand-400 focus:ring-2 focus:ring-brand-500/15 transition">
            </div>
            </div>
            <div>
                <label class="block text-xs font-medium text-gray-600 mb-1">Email <span class="text-gray-400 font-normal">(used to log in)</span></label>
                <input type="email" name="email" value="{{ old('email') }}" required
                    class="w-full px-3 py-2 rounded-lg border-gray-200 text-sm placeholder:text-gray-300 focus:border-brand-400 focus:ring-2 focus:ring-brand-500/15 transition">
                @error('email') <p class="text-xs text-red-600 mt-1">{{ $message }}</p> @enderror
            </div>
            <div>
                <label class="block text-xs font-medium text-gray-600 mb-1">Password</label>
                <input type="text" name="password" value="{{ old('password') }}" required minlength="8"
                    placeholder="At least 8 characters — share it with the staff member"
                    class="w-full px-3 py-2 rounded-lg border-gray-200 text-sm placeholder:text-gray-300 focus:border-brand-400 focus:ring-2 focus:ring-brand-500/15 transition">
                @error('password') <p class="text-xs text-red-600 mt-1">{{ $message }}</p> @enderror
            </div>
            <div id="modalOfficeField" class="hidden">
                <label class="block text-xs font-medium text-gray-600 mb-1">Office <span class="text-gray-400 font-normal">(required for supervisors)</span></label>
                <select name="office_id" class="w-full px-3 py-2 rounded-lg border-gray-200 text-sm focus:border-brand-400 focus:ring-2 focus:ring-brand-500/15 transition">
                    <option value="">Select office</option>
                    @foreach($offices as $office)
                        <option value="{{ $office->id }}" {{ old('office_id') == $office->id ? 'selected' : '' }}>
                            {{ $office->name }} ({{ $office->type_label }})
                        </option>
                    @endforeach
                </select>
                @error('office_id') <p class="text-xs text-red-600 mt-1">{{ $message }}</p> @enderror
            </div>
            <label class="flex items-center gap-2 text-sm text-gray-600">
                <input type="hidden" name="is_active" value="0">
                <input type="checkbox" name="is_active" value="1" {{ old('is_active', true) ? 'checked' : '' }}
                    class="rounded border-gray-300 text-brand-600 focus:ring-brand-500/20">
                Account active (can log in)
            </label>
            <div class="flex gap-2 pt-2">
                <button type="submit" class="bg-brand-600 hover:bg-brand-700 text-white text-sm font-medium px-4 py-2 rounded-lg transition">Create Account</button>
                <button type="button" onclick="document.getElementById('addStaffModal').classList.add('hidden')" class="text-sm font-medium px-4 py-2 rounded-lg text-gray-500 hover:bg-gray-100 transition">Cancel</button>
            </div>
        </form>
    </div>
</div>

@include('partials.confirm-modal')

@push('scripts')
<script>
    // Office scanner accounts are stations, not people — picking the role
    // hides the person fields and college, and shows the office instead.
    function syncModalStaffRoleFields(role) {
        var isOffice = role === 'office';
        document.getElementById('modalOfficeField').classList.toggle('hidden', ! (isOffice || role === 'supervisor'));
        document.getElementById('modalPersonFields').classList.toggle('hidden', isOffice);
        document.getElementById('modalCollegeField').classList.toggle('hidden', isOffice);
    }
</script>
@endpush
@endsection
