@extends('layouts.app')

@section('title', 'Manage Interns')

@section('content')
<div class="flex flex-wrap items-center justify-between gap-3 mb-6">
    <div>
        <h1 class="text-xl font-semibold tracking-tight text-gray-900">Interns</h1>
        <p class="text-sm text-gray-500 mt-0.5" id="internsCount">{{ $interns->total() }} intern{{ $interns->total() === 1 ? '' : 's' }}</p>
    </div>
    <button type="button" onclick="document.getElementById('addInternModal').classList.remove('hidden')"
        class="inline-flex items-center gap-1.5 bg-brand-600 hover:bg-brand-700 text-white text-sm font-medium px-3.5 py-2 rounded-lg transition">+ Add Intern</button>
</div>

{{-- Filters --}}
<form method="GET" class="mb-5 flex flex-wrap items-center gap-2">
    <input type="text" id="searchInput" name="search" value="{{ request('search') }}" placeholder="Search name, email, or student ID…"
        class="flex-1 min-w-[220px] max-w-sm px-3.5 py-2 rounded-lg border-gray-200 text-sm placeholder:text-gray-400 focus:border-brand-400 focus:ring-2 focus:ring-brand-500/15 transition">



    <select name="sort" onchange="this.form.submit()" class="px-3 py-2 rounded-lg border-gray-200 text-sm text-gray-600 focus:border-brand-400 focus:ring-2 focus:ring-brand-500/15 transition">
        <option value="name" {{ request('sort', 'name') === 'name' ? 'selected' : '' }}>Sort: Name</option>
        <option value="student_id" {{ request('sort') === 'student_id' ? 'selected' : '' }}>Sort: Student ID</option>
        <option value="office" {{ request('sort') === 'office' ? 'selected' : '' }}>Sort: Office</option>
        <option value="coordinator" {{ request('sort') === 'coordinator' ? 'selected' : '' }}>Sort: Coordinator</option>
        <option value="hours" {{ request('sort') === 'hours' ? 'selected' : '' }}>Sort: Hours</option>
        <option value="status" {{ request('sort') === 'status' ? 'selected' : '' }}>Sort: Status</option>
    </select>

    @if(request()->anyFilled(['search','sort']))
        <a href="{{ route('admin.interns.index') }}" class="text-sm font-medium text-gray-400 hover:text-gray-700 px-2 py-2">Clear</a>
    @endif
</form>

@include('admin.interns.partials.list', ['interns' => $interns, 'sort' => $sort, 'dir' => $dir])

{{-- Soft-deleted interns, restorable — and permanently removable.
     Scoped to the viewer: supervisors and coordinators only ever see
     their own archived interns. --}}
@php
    $deletedInterns = App\Models\User::onlyTrashed()
        ->where('role', 'intern')
        ->forStaff(auth()->user())
        ->orderBy('last_name')
        ->get();
@endphp
@if($deletedInterns->isNotEmpty())
    <div class="mt-8 bg-white border border-gray-200 rounded-xl overflow-x-auto">
        <div class="px-5 py-3.5 border-b border-gray-100 flex items-center justify-between">
            <h2 class="text-sm font-semibold text-gray-900">Deleted <span class="text-gray-400 font-normal">({{ $deletedInterns->count() }})</span></h2>
            <p class="text-xs text-gray-400">Hidden from the system and can't log in — restoring brings back their hours and logs.</p>
        </div>
        <table class="w-full text-sm">
            <tbody class="divide-y divide-gray-100">
                @foreach($deletedInterns as $intern)
                    <tr class="hover:bg-gray-50/60 transition-colors">
                        <td class="px-5 py-3">
                            <p class="font-medium text-gray-400 line-through">{{ $intern->full_name }}</p>
                            <p class="text-xs text-gray-300">{{ $intern->student_id }} · {{ $intern->email }}</p>
                        </td>
                        <td class="px-4 py-3 text-gray-400">{{ $intern->office?->name ?? '—' }}</td>
                        <td class="px-4 py-3 text-gray-400">{{ $intern->coordinator?->full_name ?? '—' }}</td>
                        <td class="px-4 py-3 text-xs text-gray-400">deleted {{ $intern->deleted_at?->diffForHumans() }}</td>
                        <td class="px-5 py-3 text-right whitespace-nowrap">
                            <form method="POST" action="{{ route('admin.interns.restore', $intern->id) }}" class="inline">
                                @csrf
                                <button class="text-xs font-medium text-brand-600 hover:underline">Restore</button>
                            </form>
                            <form method="POST" action="{{ route('admin.interns.force-delete', $intern->id) }}" class="inline"
                                  data-confirm-title="Delete permanently"
                                  data-confirm-message="Permanently delete {{ $intern->full_name }}? This cannot be undone — their logs, enrollments, and personal details will be removed with the account."
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

{{-- Add Intern Modal --}}
<div id="addInternModal" class="{{ old('first_name') ? '' : 'hidden' }} fixed inset-0 z-50 flex items-start justify-center bg-gray-900/25 px-4 pt-[5vh] overflow-y-auto"
     onclick="if(event.target === this) this.classList.add('hidden')">
    <div class="bg-white border border-gray-200 rounded-xl shadow-xl w-full max-w-2xl p-6 mb-10">
        <div class="flex items-center justify-between mb-5">
            <h3 class="font-semibold text-gray-900">Add Intern</h3>
            <button type="button" onclick="document.getElementById('addInternModal').classList.add('hidden')" class="text-gray-400 hover:text-gray-900">
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M18 6 6 18M6 6l12 12"/></svg>
            </button>
        </div>
        <form method="POST" action="{{ route('admin.interns.store') }}" class="space-y-4">
            @csrf
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <div>
                    <label class="block text-xs font-medium text-gray-600 mb-1">First Name <span class="text-gray-400 font-normal">(include middle initial)</span></label>
                    <input type="text" name="first_name" value="{{ old('first_name') }}" required placeholder="Lady Pearl T"
                        class="w-full px-3 py-2 rounded-lg border-gray-200 text-sm placeholder:text-gray-300 focus:border-brand-400 focus:ring-2 focus:ring-brand-500/15 transition">
                    @error('first_name') <p class="text-xs text-red-600 mt-1">{{ $message }}</p> @enderror
                </div>
                <div>
                    <label class="block text-xs font-medium text-gray-600 mb-1">Last Name</label>
                    <input type="text" name="last_name" value="{{ old('last_name') }}" required
                        class="w-full px-3 py-2 rounded-lg border-gray-200 text-sm placeholder:text-gray-300 focus:border-brand-400 focus:ring-2 focus:ring-brand-500/15 transition">
                    @error('last_name') <p class="text-xs text-red-600 mt-1">{{ $message }}</p> @enderror
                </div>
            </div>
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <div>
                    <label class="block text-xs font-medium text-gray-600 mb-1">Student ID</label>
                    <input type="text" name="student_id" value="{{ old('student_id') }}" required
                        class="w-full px-3 py-2 rounded-lg border-gray-200 text-sm placeholder:text-gray-300 focus:border-brand-400 focus:ring-2 focus:ring-brand-500/15 transition">
                    @error('student_id') <p class="text-xs text-red-600 mt-1">{{ $message }}</p> @enderror
                </div>
                <div>
                    <label class="block text-xs font-medium text-gray-600 mb-1">Department / Course</label>
                    <input type="text" name="department" value="{{ old('department') }}" placeholder="e.g. BSCS, BSINT"
                        class="w-full px-3 py-2 rounded-lg border-gray-200 text-sm placeholder:text-gray-300 focus:border-brand-400 focus:ring-2 focus:ring-brand-500/15 transition">
                </div>
                <div>
                    <label class="block text-xs font-medium text-gray-600 mb-1">Year Level</label>
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
                <label class="block text-xs font-medium text-gray-600 mb-1">Email <span class="text-gray-400 font-normal">(optional — auto-generated if blank)</span></label>
                <input type="email" name="email" value="{{ old('email') }}"
                    class="w-full px-3 py-2 rounded-lg border-gray-200 text-sm placeholder:text-gray-300 focus:border-brand-400 focus:ring-2 focus:ring-brand-500/15 transition">
                @error('email') <p class="text-xs text-red-600 mt-1">{{ $message }}</p> @enderror
            </div>
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <div>
                    <label class="block text-xs font-medium text-gray-600 mb-1">OJT Track</label>
                    <select name="ojt_track" required onchange="
                        document.getElementById('modalCustomLabel').classList.toggle('hidden', this.value !== 'custom');
                        document.getElementById('modalTargetHours').value = this.value === 'internship' ? 500 : '';
                    " class="w-full px-3 py-2 rounded-lg border-gray-200 text-sm focus:border-brand-400 focus:ring-2 focus:ring-brand-500/15 transition">
                        <option value="internship" {{ old('ojt_track', 'internship') === 'internship' ? 'selected' : '' }}>Internship OJT (500h)</option>
                        <option value="custom" {{ old('ojt_track') === 'custom' ? 'selected' : '' }}>Custom</option>
                    </select>
                </div>
                <div>
                    <label class="block text-xs font-medium text-gray-600 mb-1">Target Hours</label>
                    <input type="number" id="modalTargetHours" name="target_hours" required min="1" max="5000" value="{{ old('target_hours', 500) }}"
                        class="w-full px-3 py-2 rounded-lg border-gray-200 text-sm focus:border-brand-400 focus:ring-2 focus:ring-brand-500/15 transition">
                    @error('target_hours') <p class="text-xs text-red-600 mt-1">{{ $message }}</p> @enderror
                </div>
            </div>
            <div id="modalCustomLabel" class="{{ old('ojt_track') === 'custom' ? '' : 'hidden' }}">
                <label class="block text-xs font-medium text-gray-600 mb-1">Custom Track Label</label>
                <input type="text" name="custom_label" value="{{ old('custom_label') }}" placeholder="e.g. Practicum OJT"
                    class="w-full px-3 py-2 rounded-lg border-gray-200 text-sm placeholder:text-gray-300 focus:border-brand-400 focus:ring-2 focus:ring-brand-500/15 transition">
            </div>
            <div>
                <label class="block text-xs font-medium text-gray-600 mb-1">Training Starts On</label>
                <input type="date" name="training_starts_on" value="{{ old('training_starts_on', $defaultTrainingStart) }}"
                    class="w-full px-3 py-2 rounded-lg border-gray-200 text-sm focus:border-brand-400 focus:ring-2 focus:ring-brand-500/15 transition">
                <p class="text-[11px] text-gray-400 mt-1">Printed as the start month in the application letter. Defaults to the period start in Settings.</p>
            </div>
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <div>
                    <label class="block text-xs font-medium text-gray-600 mb-1">OJT Status</label>
                    <select name="ojt_status" required class="w-full px-3 py-2 rounded-lg border-gray-200 text-sm focus:border-brand-400 focus:ring-2 focus:ring-brand-500/15 transition">
                        <option value="pending" {{ old('ojt_status', 'pending') === 'pending' ? 'selected' : '' }}>Pending</option>
                        <option value="active" {{ old('ojt_status') === 'active' ? 'selected' : '' }}>Active</option>
                        <option value="completed" {{ old('ojt_status') === 'completed' ? 'selected' : '' }}>Completed</option>
                    </select>
                </div>
                <div>
                    <label class="block text-xs font-medium text-gray-600 mb-1">Batch <span class="text-gray-400 font-normal">(optional)</span></label>
                    <input type="text" name="batch" value="{{ old('batch') }}" placeholder="e.g. 2026 Summer Batch A"
                        class="w-full px-3 py-2 rounded-lg border-gray-200 text-sm placeholder:text-gray-300 focus:border-brand-400 focus:ring-2 focus:ring-brand-500/15 transition">
                </div>
            </div>
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <div>
                    <label class="block text-xs font-medium text-gray-600 mb-1">Office Placement <span class="text-gray-400 font-normal">(optional)</span></label>
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
                    <label class="block text-xs font-medium text-gray-600 mb-1">OJT Coordinator <span class="text-gray-400 font-normal">(optional)</span></label>
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
                <label class="block text-xs font-medium text-gray-600 mb-1">Password <span class="text-gray-400 font-normal">(optional — blank = their last name)</span></label>
                <input type="text" name="password" value="{{ old('password') }}" minlength="6"
                    placeholder="Leave blank to use their last name"
                    class="w-full px-3 py-2 rounded-lg border-gray-200 text-sm placeholder:text-gray-300 focus:border-brand-400 focus:ring-2 focus:ring-brand-500/15 transition">
                @error('password') <p class="text-xs text-red-600 mt-1">{{ $message }}</p> @enderror
            </div>
            <div class="flex gap-2 pt-2">
                <button type="submit" class="bg-brand-600 hover:bg-brand-700 text-white text-sm font-medium px-4 py-2 rounded-lg transition">Create Intern</button>
                <button type="button" onclick="document.getElementById('addInternModal').classList.add('hidden')" class="text-sm font-medium px-4 py-2 rounded-lg text-gray-500 hover:bg-gray-100 transition">Cancel</button>
            </div>
        </form>
    </div>
</div>

@include('partials.confirm-modal')

{{-- "Start New Set" modals — one per completed intern --}}
@foreach($interns as $intern)
    @if($intern->ojt_status === 'completed')
        <div id="newSetModal{{ $intern->id }}" class="hidden fixed inset-0 z-50 flex items-center justify-center bg-gray-900/25 px-4"
             onclick="if(event.target === this) this.classList.add('hidden')">
            <div class="bg-white border border-gray-200 rounded-xl shadow-xl w-full max-w-md p-6">
                <h3 class="font-semibold text-gray-900 mb-1">Start New OJT Set</h3>
                <p class="text-xs text-gray-500 mb-5 leading-relaxed">
                    {{ $intern->full_name }} completed <span class="font-medium text-gray-700">"{{ $intern->ojt_track_label }}"</span>
                    ({{ number_format($intern->accumulated_hours, 1) }}/{{ $intern->target_hours }}h). This starts a brand-new set —
                    hours begin at 0 and the new set is active right away.
                </p>
                <form method="POST" action="{{ route('admin.interns.start-new-set', $intern) }}" class="space-y-4">
                    @csrf
                    <div>
                        <label class="block text-xs font-medium text-gray-600 mb-1">New Track</label>
                        <select name="ojt_track" required onchange="
                            document.getElementById('customLabel{{ $intern->id }}').classList.toggle('hidden', this.value !== 'custom');
                            document.getElementById('targetHours{{ $intern->id }}').value = this.value === 'internship' ? 500 : '';
                        " class="w-full px-3 py-2 rounded-lg border-gray-200 text-sm focus:border-brand-400 focus:ring-2 focus:ring-brand-500/15 transition">
                            <option value="internship">Internship OJT</option>
                            <option value="custom">Custom</option>
                        </select>
                    </div>
                    <div id="customLabel{{ $intern->id }}" class="hidden">
                        <label class="block text-xs font-medium text-gray-600 mb-1">Custom Label</label>
                        <input type="text" name="custom_label" placeholder="e.g. Practicum OJT"
                            class="w-full px-3 py-2 rounded-lg border-gray-200 text-sm focus:border-brand-400 focus:ring-2 focus:ring-brand-500/15 transition">
                    </div>
                    <div>
                        <label class="block text-xs font-medium text-gray-600 mb-1">Target Hours</label>
                        <input type="number" id="targetHours{{ $intern->id }}" name="target_hours" required min="1" max="5000" value="300"
                            class="w-full px-3 py-2 rounded-lg border-gray-200 text-sm focus:border-brand-400 focus:ring-2 focus:ring-brand-500/15 transition">
                    </div>
                    <div class="flex gap-2 pt-1">
                        <button type="submit" class="flex-1 bg-brand-600 hover:bg-brand-700 text-white text-sm font-medium py-2 rounded-lg transition">Start Set</button>
                        <button type="button" onclick="document.getElementById('newSetModal{{ $intern->id }}').classList.add('hidden')" class="px-4 py-2 text-sm font-medium text-gray-500 hover:bg-gray-100 rounded-lg transition">Cancel</button>
                    </div>
                </form>
            </div>
        </div>
    @endif
@endforeach

@push('scripts')
<script>
    // Live search: fetch the filtered table fragment in the background and
    // swap it in, so typing is never interrupted by a page reload.
    let searchTimer;
    const searchInput = document.getElementById('searchInput');
    const tableWrap = document.getElementById('internsTableWrap');
    const countEl = document.getElementById('internsCount');

    function liveSearch() {
        const url = new URL(window.location.href);
        url.searchParams.set('search', searchInput.value.trim());
        url.searchParams.delete('page');
        url.searchParams.set('partial', '1');

        fetch(url, {
            headers: { 'X-Requested-With': 'XMLHttpRequest', 'Accept': 'text/html' }
        })
            .then(function (res) { return res.ok ? res.text() : Promise.reject(); })
            .then(function (html) {
                if (!tableWrap) { return; }
                tableWrap.innerHTML = html;

                var total = Number(tableWrap.dataset.total || 0);
                if (countEl) {
                    countEl.textContent = total === 1 ? '1 intern' : total + ' interns';
                }

                // Keep the caret where the user left off.
                searchInput.focus();
                var len = searchInput.value.length;
                searchInput.setSelectionRange(len, len);
            })
            .catch(function () {
                // Network hiccup — fall back to a plain submit so results load.
                searchInput.form.submit();
            });
    }

    searchInput?.addEventListener('input', function () {
        clearTimeout(searchTimer);
        searchTimer = setTimeout(liveSearch, 400);
    });
</script>
@endpush
@endsection
