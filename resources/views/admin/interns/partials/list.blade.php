{{-- Interns table + pagination — rendered by the full page and, with
     ?partial=1, by the live search fetch so results refresh without a
     page reload (typing is never interrupted). --}}
@php
    $baseQuery = request()->only(['search', 'status', 'track']);

    $columnUrls = [];
    foreach (['name', 'student_id', 'office', 'coordinator', 'hours', 'status'] as $col) {
        // Hours defaults to descending (highest first) on first click
        $defaultDir = $col === 'hours' ? 'desc' : 'asc';
        $colDir = ($sort === $col && $dir === $defaultDir) ? ($defaultDir === 'asc' ? 'desc' : 'asc') : $defaultDir;
        $columnUrls[$col] = route('admin.interns.index', array_merge($baseQuery, ['sort' => $col, 'dir' => $colDir]));
    }
@endphp

<div id="internsTableWrap" data-total="{{ $interns->total() }}">
    <div class="bg-white border border-gray-200 rounded-xl overflow-x-auto">
        <table class="w-full text-sm">
            <thead>
                <tr class="text-left text-[11px] font-medium uppercase tracking-widest text-gray-400 border-b border-gray-200">
                    <th class="px-5 py-3"><a href="{{ $columnUrls['name'] }}" class="text-gray-900 hover:text-gray-700 transition">Name</a></th>
                    <th class="px-4 py-3"><a href="{{ $columnUrls['student_id'] }}" class="text-gray-900 hover:text-gray-700 transition">Student ID</a></th>
                    <th class="px-4 py-3"><a href="{{ $columnUrls['office'] }}" class="text-gray-900 hover:text-gray-700 transition">Office</a></th>
                    <th class="px-4 py-3"><a href="{{ $columnUrls['coordinator'] }}" class="text-gray-900 hover:text-gray-700 transition">Coordinator</a></th>
                    <th class="px-4 py-3"><a href="{{ $columnUrls['hours'] }}" class="text-gray-900 hover:text-gray-700 transition">Hours</a></th>
                    <th class="px-4 py-3"><a href="{{ $columnUrls['status'] }}" class="text-gray-900 hover:text-gray-700 transition">Status</a></th>
                    <th class="px-5 py-3 text-right">Actions</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-100">
                @forelse($interns as $intern)
                    <tr class="hover:bg-gray-50/60 transition-colors">
                        <td class="px-5 py-3">
                            <div class="flex items-center gap-2.5">
                                @include('partials.avatar', ['user' => $intern, 'class' => 'w-8 h-8 text-[10px]'])
                                <a href="{{ route('admin.interns.show', $intern) }}" class="text-gray-900 font-medium hover:text-brand-700">{{ $intern->full_name }}</a>
                            </div>
                        </td>
                        <td class="px-4 py-3 text-gray-500 tabular-nums">{{ $intern->student_id }}</td>
                        <td class="px-4 py-3">
                            @if($intern->office)
                                <span class="text-gray-700">{{ $intern->office->name }}</span>
                                <span class="ml-1 inline-flex items-center px-1.5 py-0.5 rounded text-[10px] font-medium {{ $intern->office->type === 'external' ? 'bg-amber-50 text-amber-700' : 'bg-gray-100 text-gray-500' }}">{{ $intern->office->type_label }}</span>
                            @else
                                <span class="text-gray-300">—</span>
                            @endif
                        </td>
                        <td class="px-4 py-3 text-gray-500">{{ $intern->coordinator?->full_name ?? '—' }}</td>
                        <td class="px-4 py-3">
                            <div class="flex items-center gap-2">
                                <div class="w-20 h-1.5 bg-gray-100 rounded-full overflow-hidden">
                                    <div class="h-full rounded-full {{ $intern->is_complete ? 'bg-emerald-500' : 'bg-brand-500' }}" style="width: {{ min($intern->completion_percentage, 100) }}%"></div>
                                </div>
                                <span class="text-xs text-gray-400 tabular-nums whitespace-nowrap">{{ number_format($intern->accumulated_hours, 1) }}h</span>
                            </div>
                        </td>
                        <td class="px-4 py-3">
                            <span class="inline-flex items-center gap-1.5 text-xs font-medium
                                {{ match($intern->ojt_status) { 'active' => 'text-emerald-700', 'completed' => 'text-gray-500', default => 'text-amber-700' } }}">
                                <span class="w-1.5 h-1.5 rounded-full
                                    {{ match($intern->ojt_status) { 'active' => 'bg-emerald-500', 'completed' => 'bg-gray-300', default => 'bg-amber-500' } }}"></span>
                                {{ $intern->ojt_status_label }}
                            </span>
                        </td>
                        <td class="px-5 py-3 text-right space-x-3 whitespace-nowrap">
                            <a href="{{ route('admin.interns.show', $intern) }}" class="text-xs font-medium text-gray-500 hover:text-gray-900">Profile</a>
                            <a href="{{ route('admin.interns.show', $intern) }}#history" class="text-xs font-medium text-gray-500 hover:text-gray-900">History</a>
                            <a href="{{ route('admin.interns.edit', $intern) }}" class="text-xs font-medium text-brand-600 hover:underline">Edit</a>
                            @if($intern->ojt_status === 'completed')
                                <button type="button" onclick="document.getElementById('newSetModal{{ $intern->id }}').classList.remove('hidden')"
                                    class="text-xs font-medium text-emerald-600 hover:underline">+ New Set</button>
                            @endif
                            <form method="POST" action="{{ route('admin.interns.destroy', $intern) }}" class="inline"
                                  data-confirm-title="Delete intern"
                                  data-confirm-message="Delete {{ $intern->full_name }} ({{ $intern->student_id }})? They can no longer log in — you can restore them anytime from the Deleted section below."
                                  data-confirm-action="Delete"
                                  onsubmit="return askConfirm(this);">
                                @csrf
                                @method('DELETE')
                                <button class="text-xs font-medium text-red-500 hover:underline">Delete</button>
                            </form>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="7" class="px-5 py-10 text-center text-gray-400 text-sm">No interns match these filters.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div class="mt-4">
        {{-- `partial => null` keeps pagination links on the full page even
             when the fragment was fetched via the live search. --}}
        {{ $interns->appends(['partial' => null])->links() }}
    </div>
</div>