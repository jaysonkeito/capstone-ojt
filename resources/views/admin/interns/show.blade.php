@extends('layouts.app')

@section('title', 'Intern Profile')

@section('content')
<div>
    <div class="mb-6 flex items-start justify-between gap-3 flex-wrap">
        <div>
            <a href="{{ route('admin.interns.index') }}" class="text-xs font-medium text-gray-400 hover:text-gray-700">← Interns</a>
            <h1 class="text-xl font-semibold tracking-tight text-gray-900 mt-1">{{ $intern->full_name }}</h1>
            <p class="text-sm text-gray-500 mt-0.5 flex items-center gap-2">
                {{ $intern->student_id }} · {{ $intern->course_name }}
                <span class="inline-flex items-center gap-1.5 text-xs font-medium
                    {{ match($intern->ojt_status) { 'active' => 'text-emerald-700', 'completed' => 'text-gray-500', default => 'text-amber-700' } }}">
                    <span class="w-1.5 h-1.5 rounded-full
                        {{ match($intern->ojt_status) { 'active' => 'bg-emerald-500', 'completed' => 'bg-gray-300', default => 'bg-amber-500' } }}"></span>
                    {{ $intern->ojt_status_label }}
                </span>
            </p>
        </div>
        <div class="flex gap-2 flex-wrap">
            <a href="{{ route('admin.interns.edit', $intern) }}"
                class="bg-brand-600 hover:bg-brand-700 text-white text-sm font-medium px-4 py-2 rounded-lg transition">Edit Intern</a>
        </div>
    </div>

    {{-- Tab bar — switching tabs is client-side; no navigation happens. --}}
    <div class="border-b border-gray-200 mb-6" role="tablist">
        <nav class="flex flex-wrap gap-1 -mb-px">
            @foreach(['profile' => 'Intern\'s Profile', 'history' => 'Duty History', 'timesheet' => 'Time Frame', 'journal' => 'Daily Journal', 'documents' => 'Documents'] as $tabKey => $tabLabel)
                <button type="button" role="tab" data-tab-button="{{ $tabKey }}"
                    onclick="switchInternTab('{{ $tabKey }}')"
                    class="px-4 py-2.5 text-sm font-medium border-b-2 transition
                        {{ $tabKey === 'profile'
                            ? 'border-brand-600 text-brand-700'
                            : 'border-transparent text-gray-500 hover:text-gray-900 hover:border-gray-300' }}">
                    {{ $tabLabel }}
                </button>
            @endforeach
        </nav>
    </div>

    {{-- ============ Tab 1: Intern's Profile ============ --}}
    <div data-tab-panel="profile">
        {{-- Identity card --}}
        <div class="bg-white border border-gray-200 rounded-xl p-6 mb-5">
            <div class="flex items-center gap-4">
                @include('partials.avatar', ['user' => $intern, 'class' => 'w-14 h-14 text-base'])
                <div class="min-w-0">
                    <h2 class="text-lg font-semibold text-gray-900 truncate">{{ $intern->full_name }}</h2>
                    <p class="text-sm text-gray-500">{{ $intern->student_id }} · {{ $intern->course_name }}</p>
                </div>
            </div>

            <dl class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-x-6 gap-y-4 mt-6">
                <div>
                    <dt class="text-[11px] font-medium uppercase tracking-widest text-gray-400">First Name</dt>
                    <dd class="text-sm text-gray-900 mt-0.5">{{ $intern->first_name ?: '—' }}</dd>
                </div>
                <div>
                    <dt class="text-[11px] font-medium uppercase tracking-widest text-gray-400">Last Name</dt>
                    <dd class="text-sm text-gray-900 mt-0.5">{{ $intern->last_name ?: '—' }}</dd>
                </div>
                <div>
                    <dt class="text-[11px] font-medium uppercase tracking-widest text-gray-400">Student ID</dt>
                    <dd class="text-sm text-gray-900 mt-0.5 tabular-nums">{{ $intern->student_id ?? '—' }}</dd>
                </div>
                <div>
                    <dt class="text-[11px] font-medium uppercase tracking-widest text-gray-400">Email</dt>
                    <dd class="text-sm text-gray-900 mt-0.5 break-all">{{ $intern->email ?? '—' }}</dd>
                </div>
                <div>
                    <dt class="text-[11px] font-medium uppercase tracking-widest text-gray-400">Department / Course</dt>
                    <dd class="text-sm text-gray-900 mt-0.5">{{ $intern->department ? $intern->course_name : '—' }}</dd>
                </div>
                <div>
                    <dt class="text-[11px] font-medium uppercase tracking-widest text-gray-400">Year Level</dt>
                    <dd class="text-sm text-gray-900 mt-0.5">{{ $intern->year_level ? $intern->year_level_roman : '—' }}</dd>
                </div>
                <div>
                    <dt class="text-[11px] font-medium uppercase tracking-widest text-gray-400">Batch</dt>
                    <dd class="text-sm text-gray-900 mt-0.5">{{ $intern->batch ?? '—' }}</dd>
                </div>
                <div>
                    <dt class="text-[11px] font-medium uppercase tracking-widest text-gray-400">Office Placement</dt>
                    <dd class="text-sm text-gray-900 mt-0.5">
                        @if($intern->office)
                            {{ $intern->office->name }}
                            <span class="ml-1 inline-flex items-center px-1.5 py-0.5 rounded text-[10px] font-medium {{ $intern->office->type === 'external' ? 'bg-amber-50 text-amber-700' : 'bg-gray-100 text-gray-500' }}">{{ $intern->office->type_label }}</span>
                        @else
                            —
                        @endif
                    </dd>
                </div>
                <div>
                    <dt class="text-[11px] font-medium uppercase tracking-widest text-gray-400">OJT Coordinator</dt>
                    <dd class="text-sm text-gray-900 mt-0.5">{{ $intern->coordinator?->full_name ?? '—' }}</dd>
                </div>
                <div>
                    <dt class="text-[11px] font-medium uppercase tracking-widest text-gray-400">Account Status</dt>
                    <dd class="text-sm mt-0.5 {{ $intern->is_active ? 'text-emerald-700' : 'text-red-600' }}">
                        {{ $intern->is_active ? 'Active — can log in' : 'Inactive — cannot log in' }}
                    </dd>
                </div>
                <div>
                    <dt class="text-[11px] font-medium uppercase tracking-widest text-gray-400">Password</dt>
                    <dd class="text-sm text-gray-900 mt-0.5">
                        {{ $intern->password_changed_at ? 'Changed '.$intern->password_changed_at->format('M j, Y') : 'Still the default (their last name)' }}
                    </dd>
                </div>
                <div>
                    <dt class="text-[11px] font-medium uppercase tracking-widest text-gray-400">Registered</dt>
                    <dd class="text-sm text-gray-900 mt-0.5">{{ $intern->created_at?->format('M j, Y') ?? '—' }}</dd>
                </div>
            </dl>
        </div>

        <div class="grid grid-cols-1 xl:grid-cols-2 gap-5 mb-5">
            {{-- OJT sets --}}
            <div class="bg-white border border-gray-200 rounded-xl p-6">
                <h3 class="text-sm font-semibold text-gray-900 mb-4">OJT Sets</h3>

                @forelse($intern->enrollments as $set)
                    <div class="py-3 {{ ! $loop->first ? 'border-t border-gray-100' : '' }}">
                        <div class="flex flex-wrap items-center gap-x-3 gap-y-1">
                            <p class="text-sm font-medium text-gray-900">{{ $set->label }}</p>
                            <span class="inline-flex items-center px-1.5 py-0.5 rounded text-[10px] font-medium
                                {{ $set->status === 'active' ? 'bg-emerald-50 text-emerald-700' : ($set->status === 'completed' ? 'bg-gray-100 text-gray-500' : 'bg-amber-50 text-amber-700') }}">
                                {{ $set->status_label }}
                            </span>
                            <span class="text-xs text-gray-400">Started {{ $set->started_at?->format('M j, Y') ?? '—' }}@if($set->completed_at) · Completed {{ $set->completed_at->format('M j, Y') }}@endif</span>
                        </div>
                        <div class="flex items-center gap-2 mt-2">
                            <div class="w-48 h-1.5 bg-gray-100 rounded-full overflow-hidden">
                                <div class="h-full rounded-full {{ $set->is_complete ? 'bg-emerald-500' : 'bg-brand-500' }}"
                                    style="width: {{ min($set->completion_percentage, 100) }}%"></div>
                            </div>
                            <span class="text-xs text-gray-400 tabular-nums">
                                {{ number_format($set->accumulated_hours, 1) }}/{{ $set->target_hours }}h · {{ $set->logs->count() }} duty day{{ $set->logs->count() === 1 ? '' : 's' }}
                            </span>
                        </div>
                    </div>
                @empty
                    <p class="text-sm text-gray-400">No OJT sets recorded.</p>
                @endforelse
            </div>

            {{-- Personal information (intern-entered) --}}
            <div class="bg-white border border-gray-200 rounded-xl p-6">
                <h3 class="text-sm font-semibold text-gray-900 mb-4">Personal Information <span class="text-gray-400 font-normal">(intern-entered)</span></h3>

                @if($intern->personalInfo)
                    @php $info = $intern->personalInfo; @endphp
                    <dl class="grid grid-cols-1 sm:grid-cols-2 gap-x-6 gap-y-4">
                        <div>
                            <dt class="text-[11px] font-medium uppercase tracking-widest text-gray-400">Middle Name</dt>
                            <dd class="text-sm text-gray-900 mt-0.5">{{ $info->middle_name ?? '—' }}</dd>
                        </div>
                        <div>
                            <dt class="text-[11px] font-medium uppercase tracking-widest text-gray-400">Birthdate</dt>
                            <dd class="text-sm text-gray-900 mt-0.5">{{ $info->birthdate?->format('M j, Y') ?? '—' }}</dd>
                        </div>
                        <div>
                            <dt class="text-[11px] font-medium uppercase tracking-widest text-gray-400">Sex</dt>
                            <dd class="text-sm text-gray-900 mt-0.5">{{ $info->sex ?? '—' }}</dd>
                        </div>
                        <div>
                            <dt class="text-[11px] font-medium uppercase tracking-widest text-gray-400">Civil Status</dt>
                            <dd class="text-sm text-gray-900 mt-0.5">{{ $info->civil_status ?? '—' }}</dd>
                        </div>
                        <div>
                            <dt class="text-[11px] font-medium uppercase tracking-widest text-gray-400">Present Address</dt>
                            <dd class="text-sm text-gray-900 mt-0.5">{{ $info->present_address ?? '—' }}</dd>
                        </div>
                        <div>
                            <dt class="text-[11px] font-medium uppercase tracking-widest text-gray-400">Present Contact</dt>
                            <dd class="text-sm text-gray-900 mt-0.5 tabular-nums">{{ $info->present_contact ?? '—' }}</dd>
                        </div>
                        <div>
                            <dt class="text-[11px] font-medium uppercase tracking-widest text-gray-400">Permanent Address</dt>
                            <dd class="text-sm text-gray-900 mt-0.5">{{ $info->permanent_address ?? '—' }}</dd>
                        </div>
                        <div>
                            <dt class="text-[11px] font-medium uppercase tracking-widest text-gray-400">Permanent Contact</dt>
                            <dd class="text-sm text-gray-900 mt-0.5 tabular-nums">{{ $info->permanent_contact ?? '—' }}</dd>
                        </div>
                        <div>
                            <dt class="text-[11px] font-medium uppercase tracking-widest text-gray-400">Father's Name</dt>
                            <dd class="text-sm text-gray-900 mt-0.5">{{ $info->father_name ?? '—' }}</dd>
                        </div>
                        <div>
                            <dt class="text-[11px] font-medium uppercase tracking-widest text-gray-400">Mother's Name</dt>
                            <dd class="text-sm text-gray-900 mt-0.5">{{ $info->mother_name ?? '—' }}</dd>
                        </div>
                        <div>
                            <dt class="text-[11px] font-medium uppercase tracking-widest text-gray-400">Guardian</dt>
                            <dd class="text-sm text-gray-900 mt-0.5">
                                {{ $info->guardian_name ?: '—' }}@if($info->guardian_contact) · {{ $info->guardian_contact }}@endif
                            </dd>
                        </div>
                        <div>
                            <dt class="text-[11px] font-medium uppercase tracking-widest text-gray-400">Emergency contact</dt>
                            <dd class="text-sm text-gray-900 mt-0.5">
                                {{ $info->emergency_name ?: $info->guardian_name ?: '—' }}@if($info->emergency_relationship) · {{ $info->emergency_relationship }}@endif
                                @if($info->emergency_contact)
                                    · {{ $info->emergency_contact }}
                                @elseif($info->emergency_address)
                                    · {{ $info->emergency_address }}
                                @endif
                            </dd>
                        </div>
                    </dl>
                @else
                    <p class="text-sm text-gray-400">No personal information on file yet — the intern fills this in from their
                        <a href="{{ route('intern.personal-information.edit') }}" class="text-brand-600 hover:underline">Personal Information</a> page.</p>
                @endif
            </div>
        </div>
    </div>

    {{-- ============ Tab 2: Duty History ============ --}}
    <div data-tab-panel="history" class="hidden">
        <div class="bg-white border border-gray-200 rounded-xl overflow-x-auto">
            <div class="px-5 py-3.5 border-b border-gray-100 flex flex-wrap items-center justify-between gap-2">
                <h2 class="text-sm font-semibold text-gray-900">Duty History — current set</h2>
                <div class="flex items-center gap-3">
                    <span class="text-xs text-gray-400">{{ $logs->count() }} entr{{ $logs->count() === 1 ? 'y' : 'ies' }} · {{ number_format($intern->accumulated_hours, 1) }}h rendered</span>
                    <a href="{{ route('admin.logs.show', $intern) }}"
                        class="text-xs font-medium text-brand-600 hover:underline">Open full history →</a>
                </div>
            </div>
            <table class="w-full text-sm">
                <thead>
                    <tr class="text-left text-[11px] font-medium uppercase tracking-widest text-gray-400 border-b border-gray-200">
                        <th class="px-5 py-3">Date</th>
                        <th class="px-4 py-3">AM</th>
                        <th class="px-4 py-3">PM</th>
                        <th class="px-4 py-3 text-right">Hours</th>
                        <th class="px-4 py-3 text-right">OT</th>
                        <th class="px-4 py-3">Attendance</th>
                        <th class="px-4 py-3">Review</th>
                        <th class="px-4 py-3">Notes</th>
                        <th class="px-5 py-3 text-right">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    @php $t = fn($v) => $v ? \Illuminate\Support\Carbon::parse($v)->format('g:i A') : '—'; @endphp
                    @forelse($logs as $log)
                        <tr class="hover:bg-gray-50/60 transition-colors {{ $log->status === 'pending' ? 'bg-amber-50/40' : '' }}">
                            <td class="px-5 py-3 whitespace-nowrap font-medium text-gray-700">{{ $log->date->format('M d, Y') }}</td>
                            <td class="px-4 py-3 text-gray-500 tabular-nums whitespace-nowrap">
                                {{ $t($log->am_time_in) }} – {{ $t($log->am_time_out) }}
                                @if($log->am_time_in_2)
                                    <span class="block text-[10px] text-gray-400">↩ {{ $t($log->am_time_in_2) }}{{ $log->am_time_out_2 ? ' – '.$t($log->am_time_out_2) : '' }}</span>
                                @endif
                            </td>
                            <td class="px-4 py-3 text-gray-500 tabular-nums whitespace-nowrap">
                                {{ $t($log->pm_time_in) }} – {{ $t($log->pm_time_out) }}
                                @if($log->pm_time_in_2)
                                    <span class="block text-[10px] text-gray-400">↩ {{ $t($log->pm_time_in_2) }}{{ $log->pm_time_out_2 ? ' – '.$t($log->pm_time_out_2) : '' }}</span>
                                @endif
                            </td>
                            <td class="px-4 py-3 text-right font-medium text-gray-900 tabular-nums">{{ number_format($log->hours_rendered, 2) }}h</td>
                            <td class="px-4 py-3 text-right text-amber-600 tabular-nums">{{ $log->has_overtime ? '+'.number_format($log->overtime_hours, 2) : '—' }}</td>
                            <td class="px-4 py-3">
                                <span class="inline-flex items-center px-2 py-0.5 rounded-md text-xs font-medium {{ $log->attendance_status['class'] }}">{{ $log->attendance_status['label'] }}</span>
                            </td>
                            <td class="px-4 py-3">
                                <span class="inline-flex items-center px-2 py-0.5 rounded-md text-xs font-medium {{ $log->review_status['class'] }}"
                                    @if($log->status !== 'approved' && $log->review_comment) title="By {{ $log->reviewedBy?->full_name ?? '—' }}: {{ $log->review_comment }}" @endif
                                >{{ $log->review_status['label'] }}</span>
                            </td>
                            <td class="px-4 py-3">
                                <div class="max-w-[9rem] truncate text-gray-500" title="{{ $log->notes }}">{{ $log->notes ?: '—' }}</div>
                            </td>
                            <td class="px-5 py-3 text-right whitespace-nowrap">
                                <button type="button" onclick="openEditModal({{ $log->id }})"
                                    class="inline-flex items-center justify-center w-7 h-7 rounded-md text-gray-400 hover:text-brand-600 hover:bg-brand-50 transition" title="Edit entry">
                                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 20h9"/><path d="M16.5 3.5a2.12 2.12 0 0 1 3 3L7 19l-4 1 1-4Z"/></svg>
                                </button>
                                <form method="POST" action="{{ route('admin.logs.destroy', $log) }}" class="inline"
                                      data-confirm-title="Delete entry"
                                      data-confirm-message="Delete {{ $intern->full_name }}'s entry for {{ $log->date->format('M d, Y') }}? This cannot be undone."
                                      data-confirm-action="Delete"
                                      onsubmit="return askConfirm(this);">
                                    @csrf
                                    @method('DELETE')
                                    <button class="inline-flex items-center justify-center w-7 h-7 rounded-md text-gray-400 hover:text-red-600 hover:bg-red-50 transition" title="Delete entry">
                                        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M3 6h18"/><path d="M8 6V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2m3 0-1 14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2L4 6"/></svg>
                                    </button>
                                </form>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="9" class="px-5 py-10 text-center text-gray-400 text-sm">No duty days recorded in this set yet.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    {{-- ============ Tab 3: Time Frame ============ --}}
    <div data-tab-panel="timesheet" class="hidden">
        <div class="grid grid-cols-1 xl:grid-cols-3 gap-5 mb-5">
            <div class="bg-white border border-gray-200 rounded-xl p-6">
                <h3 class="text-sm font-semibold text-gray-900 mb-4">Set Totals</h3>
                <dl class="space-y-3">
                    <div class="flex justify-between items-baseline">
                        <dt class="text-xs uppercase tracking-widest text-gray-400">Duty Days</dt>
                        <dd class="text-sm font-semibold text-gray-900 tabular-nums">{{ $timesheetTotals['days'] }}</dd>
                    </div>
                    <div class="flex justify-between items-baseline">
                        <dt class="text-xs uppercase tracking-widest text-gray-400">Regular Hours</dt>
                        <dd class="text-sm font-semibold text-gray-900 tabular-nums">{{ number_format($timesheetTotals['regular'], 2) }}h</dd>
                    </div>
                    <div class="flex justify-between items-baseline">
                        <dt class="text-xs uppercase tracking-widest text-gray-400">Overtime</dt>
                        <dd class="text-sm font-semibold text-amber-600 tabular-nums">{{ number_format($timesheetTotals['overtime'], 2) }}h</dd>
                    </div>
                    <div class="flex justify-between items-baseline border-t border-gray-100 pt-3">
                        <dt class="text-xs uppercase tracking-widest text-gray-400">Total Rendered</dt>
                        <dd class="text-base font-semibold text-gray-900 tabular-nums">{{ number_format($timesheetTotals['total'], 2) }}h</dd>
                    </div>
                </dl>
                <div class="mt-5">
                    <div class="flex justify-between items-baseline mb-1.5">
                        <span class="text-xs text-gray-400">of {{ $intern->target_hours }}h target</span>
                        <span class="text-xs font-semibold tabular-nums {{ $intern->is_complete ? 'text-emerald-600' : 'text-gray-900' }}">{{ $intern->completion_percentage }}%</span>
                    </div>
                    <div class="w-full bg-gray-100 rounded-full h-2">
                        <div class="h-2 rounded-full {{ $intern->is_complete ? 'bg-emerald-500' : 'bg-brand-500' }}" style="width: {{ min($intern->completion_percentage, 100) }}%"></div>
                    </div>
                </div>
            </div>

            <div class="bg-white border border-gray-200 rounded-xl p-6 xl:col-span-2">
                <h3 class="text-sm font-semibold text-gray-900 mb-4">Document</h3>
                <p class="text-sm text-gray-500 mb-4">The Word time frame fills from these rows — every duty day of the current set with the running totals, signed by the intern, the office supervisor, and the coordinator.</p>
                <dl class="grid grid-cols-1 sm:grid-cols-2 gap-x-6 gap-y-3 mb-5">
                    <div>
                        <dt class="text-[11px] font-medium uppercase tracking-widest text-gray-400">Time Frame prepared by</dt>
                        <dd class="text-sm text-gray-900 mt-0.5">{{ $intern->display_name }}</dd>
                    </div>
                    <div>
                        <dt class="text-[11px] font-medium uppercase tracking-widest text-gray-400">Approved by (office)</dt>
                        <dd class="text-sm text-gray-900 mt-0.5">{{ $intern->office?->supervisors->first()?->full_name ?? '—' }}</dd>
                    </div>
                    <div>
                        <dt class="text-[11px] font-medium uppercase tracking-widest text-gray-400">Reviewed by (coordinator)</dt>
                        <dd class="text-sm text-gray-900 mt-0.5">{{ $intern->coordinator?->full_name ?? '—' }}</dd>
                    </div>
                    <div>
                        <dt class="text-[11px] font-medium uppercase tracking-widest text-gray-400">Hours certified</dt>
                        <dd class="text-sm text-gray-900 mt-0.5">
                            @if($certification)
                                {{ $certification->supervisor?->full_name ?? '—' }} · {{ $certification->period_label }}
                            @else
                                <span class="text-gray-400">No period certified yet</span>
                            @endif
                        </dd>
                    </div>
                </dl>
                <a href="{{ route('admin.interns.timesheet', $intern) }}" target="_blank"
                    class="inline-flex items-center gap-1.5 bg-brand-600 hover:bg-brand-700 text-white text-sm font-medium px-4 py-2 rounded-lg transition">
                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><path d="M7 10l5 5 5-5"/><path d="M12 15V3"/></svg>
                    Download Time Frame (.docx)
                </a>
                <p class="text-[11px] text-gray-400 mt-2">Requires a Time Frame template under Templates — the intern can download the same document from their dashboard.</p>
            </div>
        </div>

        <div class="bg-white border border-gray-200 rounded-xl overflow-x-auto">
            <div class="px-5 py-3.5 border-b border-gray-100">
                <h2 class="text-sm font-semibold text-gray-900">As it prints</h2>
            </div>
            <table class="w-full text-sm">
                <thead>
                    <tr class="text-left text-[11px] font-medium uppercase tracking-widest text-gray-400 border-b border-gray-200">
                        <th class="px-5 py-3">Date</th>
                        <th class="px-4 py-3">Clocked Time</th>
                        <th class="px-5 py-3 text-right">Hours</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    @forelse($timesheetRows as $row)
                        <tr class="hover:bg-gray-50/60 transition-colors">
                            <td class="px-5 py-3 whitespace-nowrap font-medium text-gray-700 tabular-nums">{{ $row['date'] }}</td>
                            <td class="px-4 py-3 text-gray-500 tabular-nums">{{ $row['time'] }}</td>
                            <td class="px-5 py-3 text-right font-medium text-gray-900 tabular-nums">{{ $row['hours'] }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="3" class="px-5 py-10 text-center text-gray-400 text-sm">No duty days recorded in this set yet.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    {{-- ============ Tab 4: Daily Journal ============ --}}
    <div data-tab-panel="journal" class="hidden">
        <div class="flex flex-wrap items-center justify-between gap-2 mb-4">
            <p class="text-sm text-gray-500">Every duty day of the current set, in the same layout the intern sees — click a photo to view it full size, or edit an entry directly.</p>
            <span class="text-xs text-gray-400 tabular-nums">
                {{ $logs->filter(fn ($log) => $log->has_journal)->count() }} of {{ $logs->count() }} duty da{{ $logs->count() === 1 ? 'y' : 'ys' }} with a journal
            </span>
        </div>

        <div class="grid grid-cols-2 md:grid-cols-3 xl:grid-cols-4 2xl:grid-cols-5 gap-3 sm:gap-4">
            @forelse($logs as $log)
                <div class="bg-white border border-gray-200 rounded-xl overflow-hidden flex flex-col">
                    {{-- Photo, or the uniform placeholder --}}
                    <div class="relative aspect-square bg-gray-100">
                        @if($log->has_journal)
                            <a href="{{ $log->photo_url }}" target="_blank" title="View full photo — {{ $log->date->format('M d, Y') }}">
                                <img src="{{ $log->photo_url }}" alt="Duty photo {{ $log->date->format('M d, Y') }}"
                                     class="w-full h-full object-cover hover:opacity-90 transition">
                            </a>
                        @else
                            <div class="absolute inset-0 flex flex-col items-center justify-center text-gray-300">
                                <svg width="26" height="26" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="3" width="18" height="18" rx="2"/><circle cx="9" cy="9" r="2"/><path d="m21 15-4.35-4.35a1.5 1.5 0 0 0-2.12 0L5 20"/></svg>
                                <p class="text-[10px] uppercase tracking-wider mt-1.5">No photo yet</p>
                            </div>
                        @endif

                        @if($log->journal_removed)
                            <span class="absolute top-1.5 left-1.5 inline-flex items-center px-1.5 py-0.5 rounded text-[10px] font-medium bg-red-50 text-red-600 border border-red-100">Removed</span>
                        @elseif($log->photo_required)
                            <span class="absolute top-1.5 left-1.5 inline-flex items-center px-1.5 py-0.5 rounded text-[10px] font-medium bg-amber-50 text-amber-700 border border-amber-100">Awaiting journal</span>
                        @endif

                        <span class="absolute bottom-1.5 right-1.5 inline-flex items-center px-1.5 py-0.5 rounded text-[10px] font-medium bg-gray-900/70 text-white tabular-nums">
                            {{ number_format($log->hours_rendered, 2) }}h
                            @if($log->has_overtime)
                                · +{{ number_format($log->overtime_hours, 2) }}h OT
                            @endif
                        </span>
                    </div>

                    {{-- Body: date, badges, journal message, actions --}}
                    <div class="px-3 py-2.5 flex flex-col flex-1">
                        <div class="flex items-center justify-between gap-2">
                            <p class="text-xs font-semibold text-gray-900 whitespace-nowrap">{{ $log->date->format('M d, Y') }}</p>
                            <span class="text-[10px] font-medium uppercase tracking-wider text-gray-400">{{ $log->date->format('D') }}</span>
                        </div>
                        <div class="flex flex-wrap items-center gap-1 mt-1">
                            <span class="inline-flex items-center px-1.5 py-0.5 rounded text-[10px] font-medium {{ $log->attendance_status['class'] }}">{{ $log->attendance_status['label'] }}</span>
                            <span class="inline-flex items-center px-1.5 py-0.5 rounded text-[10px] font-medium {{ $log->review_status['class'] }}">{{ $log->review_status['label'] }}</span>
                        </div>

                        @if($log->has_journal)
                            <p class="text-xs text-gray-600 mt-1.5 whitespace-pre-line line-clamp-3 flex-1" title="{{ $log->notes }}">{{ $log->notes ?: 'No notes for this day.' }}</p>
                        @elseif($log->journal_removed)
                            <p class="text-xs text-gray-400 italic mt-1.5 flex-1">The intern removed this journal
                                @if($log->journal_removed_at) · {{ $log->journal_removed_at->format('M d, Y') }}@endif.</p>
                        @elseif($log->photo_required)
                            <p class="text-xs text-amber-700 mt-1.5 flex-1">Awaiting journal — the intern hasn't uploaded their proof photo for this day yet.</p>
                        @else
                            <p class="text-xs text-gray-400 italic mt-1.5 flex-1">No journal for this day.</p>
                        @endif

                        <div class="mt-2 pt-2 border-t border-gray-100 flex items-center justify-between gap-2">
                            <button type="button" onclick="openEditModal({{ $log->id }})"
                                class="text-[11px] font-medium text-brand-600 hover:underline whitespace-nowrap">
                                Edit entry
                            </button>
                            <a href="{{ route('reports.show', $log) }}" target="_blank" class="text-[11px] font-medium text-gray-400 hover:text-brand-600 whitespace-nowrap" title="Export Daily Report">Daily Report</a>
                        </div>
                    </div>
                </div>
            @empty
                <div class="col-span-full bg-white border border-gray-200 rounded-xl px-6 py-16 text-center">
                    <p class="text-sm text-gray-400">No duty days recorded in this set yet.</p>
                </div>
            @endforelse
        </div>
    </div>

    {{-- ============ Tab 5: Documents — requirement submissions + review ============ --}}
    <div data-tab-panel="documents" class="hidden">
        @include('partials.intern-documents-panel', ['intern' => $intern, 'submissions' => $submissions])
    </div>
</div>

{{-- Edit-entry modals — one per duty day, shared by the Duty History and
     Daily Journal tabs (rendered once, outside the tab panels, so either
     tab's buttons open the same overlay). --}}
@foreach($logs as $log)
    @php
        // Day navigation inside the edit modal: adjacent entries by
        // date (the collection is newest-first).
        $newerLog = $loop->index > 0 ? $logs[$loop->index - 1] : null;
        $olderLog = $loop->index < $logs->count() - 1 ? $logs[$loop->index + 1] : null;
    @endphp
    @include('admin.logs.partials.edit-entry-modal', ['log' => $log, 'newerLog' => $newerLog, 'olderLog' => $olderLog])
@endforeach

@include('partials.confirm-modal')

@push('scripts')
<script>
const TABS = ['profile', 'history', 'timesheet', 'journal', 'documents'];

// Hop from one day's edit modal to the adjacent day's without closing.
function switchEditModal(fromId, toId) {
    const current = document.getElementById('editModal' + fromId);
    const target = document.getElementById('editModal' + toId);
    if (! current || ! target) { return; }

    current.classList.add('hidden');
    target.classList.remove('hidden');
    const body = target.querySelector('.overflow-y-auto');
    if (body) { body.scrollTop = 0; }
}

// Open a day's edit modal from either the Duty History or Daily Journal tab.
function openEditModal(id) {
    const modal = document.getElementById('editModal' + id);
    if (modal) { modal.classList.remove('hidden'); }
}

// Close the journal export dropdown when clicking anywhere outside it.
document.addEventListener('click', (event) => {
    if (! panel || panel.classList.contains('hidden')) { return; }
    if (! panel.parentElement.contains(event.target)) { panel.classList.add('hidden'); }
});

function switchInternTab(key) {
    if (! TABS.includes(key)) { key = 'profile'; }

    TABS.forEach((name) => {
        const panel = document.querySelector(`[data-tab-panel="${name}"]`);
        const button = document.querySelector(`[data-tab-button="${name}"]`);
        if (! panel || ! button) { return; }

        const active = name === key;
        panel.classList.toggle('hidden', ! active);
        button.classList.toggle('border-brand-600', active);
        button.classList.toggle('text-brand-700', active);
        button.classList.toggle('border-transparent', ! active);
        button.classList.toggle('text-gray-500', ! active);
    });

    // Deep-linkable without navigating — replaceState never reloads the page.
    history.replaceState(null, '', '#' + key);
}

document.addEventListener('DOMContentLoaded', () => {
    const hash = window.location.hash.replace('#', '');
    if (hash && TABS.includes(hash) && hash !== 'profile') { switchInternTab(hash); }
});
</script>
@endpush
@endsection
