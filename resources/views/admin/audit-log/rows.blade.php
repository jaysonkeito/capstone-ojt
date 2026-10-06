@foreach($logs as $log)
    <tr class="border-b border-gray-100 align-top hover:bg-gray-50/70 audit-row" data-id="{{ $log->id }}">
        <td class="px-4 py-2.5 text-xs text-gray-500 whitespace-nowrap">{{ $log->created_at?->format('M j, Y') }}<br>{{ $log->created_at?->format('g:i:s A') }}</td>
        <td class="px-4 py-2.5">
            <p class="text-sm font-medium text-gray-900">{{ $log->user_name }}</p>
            <p class="text-[11px] text-gray-400">{{ ucfirst($log->user_role ?? '') }}</p>
        </td>
        <td class="px-4 py-2.5">
            <span class="inline-block rounded-full px-2 py-0.5 text-[11px] font-medium
                {{ match ($log->action) {
                    'created' => 'bg-green-50 text-green-700',
                    'updated' => 'bg-amber-50 text-amber-700',
                    'deleted' => 'bg-red-50 text-red-700',
                    'restored' => 'bg-blue-50 text-blue-700',
                    'time-in', 'time-out' => 'bg-emerald-50 text-emerald-700',
                    default => 'bg-gray-100 text-gray-600',
                } }}">{{ ucwords(str_replace('-', ' ', $log->action)) }}</span>
        </td>
        <td class="px-4 py-2.5">
            <p class="text-sm text-gray-900">{{ $log->subject_label ?? '—' }}</p>
            <p class="text-[11px] text-gray-400">{{ $log->subject_type }}@if($log->subject_id) #{{ $log->subject_id }}@endif</p>
        </td>
        <td class="px-4 py-2.5 text-xs text-gray-600">
            @if($log->changes)
                <details>
                    <summary class="cursor-pointer text-brand-600 select-none">{{ count($log->changes) }} field{{ count($log->changes) === 1 ? '' : 's' }} changed</summary>
                    <div class="mt-1.5 space-y-1">
                        @foreach($log->changes as $field => $change)
                            <p><span class="font-medium text-gray-700">{{ $field }}:</span>
                                <span class="text-red-600 line-through">{{ \Illuminate\Support\Str::limit((string) ($change['old'] ?? '—'), 40) }}</span>
                                → <span class="text-green-700">{{ \Illuminate\Support\Str::limit((string) ($change['new'] ?? '—'), 40) }}</span></p>
                        @endforeach
                    </div>
                </details>
            @else
                —
            @endif
        </td>
    </tr>
@endforeach
