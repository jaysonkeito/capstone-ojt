@extends('layouts.app')

@section('title', $office->name)

@section('content')
<div>
    <div class="mb-6 flex items-start justify-between gap-3 flex-wrap">
        <div>
            <a href="{{ route('admin.offices.index') }}" class="text-xs font-medium text-gray-400 hover:text-gray-700">← Offices</a>
            <h1 class="text-xl font-semibold tracking-tight text-gray-900 mt-1">{{ $office->name }}</h1>
            <p class="text-sm text-gray-500 mt-0.5 flex items-center gap-2 flex-wrap">
                <span class="inline-flex items-center px-2 py-0.5 rounded-md text-xs font-medium {{ $office->type === 'external' ? 'bg-amber-50 text-amber-700' : 'bg-gray-100 text-gray-600' }}">{{ $office->type_label }}</span>
                @if($office->city || $office->address)
                    {{ collect([$office->address, $office->city])->filter()->implode(', ') }}
                @endif
            </p>
        </div>
        <div class="flex gap-2 flex-wrap">
            <a href="{{ route('admin.offices.edit', $office) }}"
                class="bg-brand-600 hover:bg-brand-700 text-white text-sm font-medium px-4 py-2 rounded-lg transition">Edit Office</a>
        </div>
    </div>

    {{-- Tab bar — switching tabs is client-side; no navigation happens. --}}
    <div class="border-b border-gray-200 mb-6" role="tablist">
        <nav class="flex flex-wrap gap-1 -mb-px">
            @foreach(['overview' => 'Overview', 'interns' => 'Interns ('.$office->interns_count.')'] as $tabKey => $tabLabel)
                <button type="button" role="tab" data-tab-button="{{ $tabKey }}"
                    onclick="switchOfficeTab('{{ $tabKey }}')"
                    class="px-4 py-2.5 text-sm font-medium border-b-2 transition
                        {{ $tabKey === 'overview'
                            ? 'border-brand-600 text-brand-700'
                            : 'border-transparent text-gray-500 hover:text-gray-900 hover:border-gray-300' }}">
                    {{ $tabLabel }}
                </button>
            @endforeach
        </nav>
    </div>

    {{-- ============ Tab 1: Overview ============ --}}
    <div data-tab-panel="overview">
        <div class="grid grid-cols-1 xl:grid-cols-2 gap-5 mb-5">
            {{-- Placement & address --}}
            <div class="bg-white border border-gray-200 rounded-xl p-6">
                <h3 class="text-sm font-semibold text-gray-900 mb-4">Placement</h3>
                <dl class="grid grid-cols-1 sm:grid-cols-2 gap-x-6 gap-y-4">
                    <div>
                        <dt class="text-[11px] font-medium uppercase tracking-widest text-gray-400">Type</dt>
                        <dd class="text-sm text-gray-900 mt-0.5">{{ $office->type_label }}</dd>
                    </div>
                    <div>
                        <dt class="text-[11px] font-medium uppercase tracking-widest text-gray-400">Agency / Company</dt>
                        <dd class="text-sm text-gray-900 mt-0.5">{{ $office->agency ?: '—' }}</dd>
                    </div>
                    <div class="sm:col-span-2">
                        <dt class="text-[11px] font-medium uppercase tracking-widest text-gray-400">Address</dt>
                        <dd class="text-sm text-gray-900 mt-0.5">
                            {{ collect([$office->address, $office->city, $office->province, $office->postal])->filter()->implode(', ') ?: '—' }}
                        </dd>
                    </div>
                </dl>
            </div>

            {{-- Contact --}}
            <div class="bg-white border border-gray-200 rounded-xl p-6">
                <h3 class="text-sm font-semibold text-gray-900 mb-4">Contact</h3>
                <dl class="grid grid-cols-1 sm:grid-cols-2 gap-x-6 gap-y-4">
                    <div>
                        <dt class="text-[11px] font-medium uppercase tracking-widest text-gray-400">Contact Person</dt>
                        <dd class="text-sm text-gray-900 mt-0.5">{{ $office->contact_person ?: '—' }}</dd>
                    </div>
                    <div>
                        <dt class="text-[11px] font-medium uppercase tracking-widest text-gray-400">Email</dt>
                        <dd class="text-sm text-gray-900 mt-0.5 break-all">{{ $office->contact_email ?: '—' }}</dd>
                    </div>
                    <div>
                        <dt class="text-[11px] font-medium uppercase tracking-widest text-gray-400">Phone</dt>
                        <dd class="text-sm text-gray-900 mt-0.5 tabular-nums">{{ $office->contact_phone ?: '—' }}</dd>
                    </div>
                </dl>
            </div>

            {{-- Working times --}}
            <div class="bg-white border border-gray-200 rounded-xl p-6">
                <h3 class="text-sm font-semibold text-gray-900 mb-1">Working Times</h3>
                <p class="text-xs text-gray-400 mb-4">The schedule this office's interns' hours are measured against.</p>
                <div class="divide-y divide-gray-100">
                    @foreach($slots as $slot)
                        <div class="flex items-center justify-between py-2.5">
                            <p class="text-sm text-gray-500">{{ $slot['label'] }}</p>
                            <div class="flex items-center gap-2.5">
                                <span class="text-sm text-gray-900 tabular-nums">{{ $slot['effective'] ?? '—' }}</span>
                                @if($slot['office'])
                                    <span class="inline-flex items-center px-1.5 py-0.5 rounded text-[10px] font-medium bg-brand-50 text-brand-700">Office override</span>
                                @else
                                    <span class="inline-flex items-center px-1.5 py-0.5 rounded text-[10px] font-medium bg-gray-100 text-gray-500">Campus default</span>
                                @endif
                            </div>
                        </div>
                    @endforeach
                </div>
            </div>

            {{-- Kiosk tab locks --}}
            <div class="bg-white border border-gray-200 rounded-xl p-6">
                <h3 class="text-sm font-semibold text-gray-900 mb-1">Kiosk Station Tabs</h3>
                <p class="text-xs text-gray-400 mb-4">Which station tabs are locked for this office's kiosk.</p>
                <div class="divide-y divide-gray-100">
                    @foreach([['lock_scanner', 'Scanner'], ['lock_camera', 'Camera'], ['lock_manual', 'Student ID (manual entry)']] as [$key, $label])
                        <div class="flex items-center justify-between py-2.5">
                            <p class="text-sm text-gray-500">{{ $label }}</p>
                            @if($locks[$key])
                                <span class="inline-flex items-center gap-1 text-xs font-medium text-amber-700">🔒 Locked</span>
                            @else
                                <span class="inline-flex items-center gap-1 text-xs font-medium text-emerald-700">Open</span>
                            @endif
                        </div>
                    @endforeach
                </div>
            </div>

            {{-- Supervisors --}}
            <div class="bg-white border border-gray-200 rounded-xl p-6 xl:col-span-2">
                <h3 class="text-sm font-semibold text-gray-900 mb-4">Supervisors ({{ $supervisors->count() }})</h3>
                @forelse($supervisors as $supervisor)
                    <div class="flex items-center gap-3 py-2 {{ ! $loop->first ? 'border-t border-gray-100' : '' }}">
                        @include('partials.avatar', ['user' => $supervisor, 'class' => 'w-9 h-9 text-xs'])
                        <div class="min-w-0">
                            <p class="text-sm font-medium text-gray-900 truncate">{{ $supervisor->full_name }}</p>
                            <p class="text-xs text-gray-400 truncate">{{ $supervisor->email }}</p>
                        </div>
                    </div>
                @empty
                    <p class="text-sm text-gray-400">No supervisor account represents this office yet.</p>
                @endforelse
            </div>
        </div>
    </div>

    {{-- ============ Tab 2: Interns ============ --}}
    <div data-tab-panel="interns" class="hidden">
        <div class="bg-white border border-gray-200 rounded-xl overflow-x-auto">
            <table class="w-full text-sm">
                <thead>
                    <tr class="text-left text-[11px] font-medium uppercase tracking-widest text-gray-400 border-b border-gray-200">
                        <th class="px-5 py-3">Intern</th>
                        <th class="px-4 py-3">Course</th>
                        <th class="px-4 py-3">Coordinator</th>
                        <th class="px-4 py-3">Status</th>
                        <th class="px-5 py-3 text-right">Hours</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    @forelse($interns as $intern)
                        <tr class="hover:bg-gray-50/60 transition-colors">
                            <td class="px-5 py-3">
                                <a href="{{ route('admin.interns.show', $intern) }}" class="font-medium text-gray-900 hover:text-brand-700">{{ $intern->full_name }}</a>
                                @if($intern->student_id)
                                    <p class="text-xs text-gray-400 tabular-nums">{{ $intern->student_id }}</p>
                                @endif
                            </td>
                            <td class="px-4 py-3 text-gray-500 text-xs">{{ $intern->course_name }}</td>
                            <td class="px-4 py-3 text-gray-500 text-xs">{{ $intern->coordinator?->full_name ?? '—' }}</td>
                            <td class="px-4 py-3">
                                <span class="inline-flex items-center gap-1.5 text-xs font-medium
                                    {{ match($intern->ojt_status) { 'active' => 'text-emerald-700', 'completed' => 'text-gray-500', default => 'text-amber-700' } }}">
                                    <span class="w-1.5 h-1.5 rounded-full
                                        {{ match($intern->ojt_status) { 'active' => 'bg-emerald-500', 'completed' => 'bg-gray-300', default => 'bg-amber-500' } }}"></span>
                                    {{ $intern->ojt_status_label }}
                                </span>
                            </td>
                            <td class="px-5 py-3 text-right text-gray-700 tabular-nums">
                                {{ number_format($intern->accumulated_hours, 1) }}
                                <span class="text-gray-400">/ {{ $intern->target_hours }}h</span>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="5" class="px-5 py-10 text-center text-gray-400 text-sm">No interns are placed at this office.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>

<script>
    function switchOfficeTab(key) {
        document.querySelectorAll('[data-tab-panel]').forEach(panel => {
            panel.classList.toggle('hidden', panel.dataset.tabPanel !== key);
        });
        document.querySelectorAll('[data-tab-button]').forEach(button => {
            const active = button.dataset.tabButton === key;
            button.classList.toggle('border-brand-600', active);
            button.classList.toggle('text-brand-700', active);
            button.classList.toggle('border-transparent', !active);
            button.classList.toggle('text-gray-500', !active);
        });
    }
</script>
@endsection
