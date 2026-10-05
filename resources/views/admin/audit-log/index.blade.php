@extends('layouts.app')

@section('title', 'Activity Log')

@section('content')
<div>
    <div class="flex flex-wrap items-center justify-between gap-3 mb-6">
        <div>
            <h1 class="text-xl font-semibold tracking-tight text-gray-900">Activity Log</h1>
            <p class="text-sm text-gray-500 mt-0.5">Every change across accounts, duty records, requests, and templates — with who made it.</p>
        </div>
        <div class="flex items-center gap-3">
            <span id="auditLiveLabel" class="text-[11px] text-gray-400"></span>
            <label class="inline-flex items-center gap-2 text-xs font-medium text-gray-700 cursor-pointer select-none">
                <input type="checkbox" id="auditLiveToggle" checked class="rounded border-gray-300 text-brand-600 focus:ring-brand-500/30">
                Live
            </label>
        </div>
    </div>

    {{-- Filters --}}
    <form method="GET" class="flex flex-wrap items-end gap-3 mb-5">
        <div>
            <label class="block text-[11px] font-medium text-gray-500 mb-1">User</label>
            <select name="user_id" class="px-3 py-2 rounded-lg border-gray-200 text-sm">
                <option value="">Everyone</option>
                @foreach($users as $u)
                    <option value="{{ $u->user_id }}" {{ $filters['user_id'] === $u->user_id ? 'selected' : '' }}>{{ $u->user_name }}</option>
                @endforeach
            </select>
        </div>
        <div>
            <label class="block text-[11px] font-medium text-gray-500 mb-1">Action</label>
            <select name="action" class="px-3 py-2 rounded-lg border-gray-200 text-sm">
                <option value="">All actions</option>
                @foreach($actions as $action)
                    <option value="{{ $action }}" {{ $filters['action'] === $action ? 'selected' : '' }}>{{ ucwords(str_replace('-', ' ', $action)) }}</option>
                @endforeach
            </select>
        </div>
        <button type="submit" class="px-3.5 py-2 rounded-lg bg-white border border-gray-200 text-sm font-medium text-gray-700 hover:bg-gray-50 transition">Apply</button>
    </form>

    <div class="bg-white border border-gray-200 rounded-xl overflow-x-auto">
        <table class="w-full">
            <thead>
                <tr class="border-b border-gray-200 text-left text-[11px] font-semibold uppercase tracking-widest text-gray-400">
                    <th class="px-4 py-3">When</th>
                    <th class="px-4 py-3">Who</th>
                    <th class="px-4 py-3">Action</th>
                    <th class="px-4 py-3">What</th>
                    <th class="px-4 py-3">Details</th>
                </tr>
            </thead>
            <tbody id="auditLogBody">
                @include('admin.audit-log.rows', ['logs' => $logs])
            </tbody>
        </table>
        @if($logs->isEmpty())
            <p class="text-center text-sm text-gray-400 py-10">Nothing recorded yet for this filter.</p>
        @endif
    </div>
</div>

<script>
    (function () {
        const body = document.getElementById('auditLogBody');
        const toggle = document.getElementById('auditLiveToggle');
        const label = document.getElementById('auditLiveLabel');
        let lastId = Math.max(0, ...[...body.querySelectorAll('tr')].map(tr => Number(tr.dataset.id) || 0));
        let seconds = 0;

        function tickLabel() {
            seconds += 1;
            label.textContent = 'Updated ' + seconds + 's ago';
        }
        setInterval(tickLabel, 1000);

        async function poll() {
            if (toggle.checked) {
                const params = new URLSearchParams(window.location.search);
                params.set('after', lastId);
                try {
                    const res = await fetch('{{ route('admin.audit-log.entries') }}?' + params.toString());
                    const html = await res.text();
                    if (html.trim()) {
                        body.insertAdjacentHTML('afterbegin', html);
                        body.querySelectorAll('tr.audit-row').forEach(row => {
                            const id = Number(row.dataset.id) || 0;
                            if (id > lastId) {
                                lastId = id;
                                if (row.classList.contains('audit-row') && !row.dataset.seen) {
                                    row.classList.add('bg-brand-50/60');
                                    row.dataset.seen = '1';
                                    setTimeout(() => row.classList.remove('bg-brand-50/60'), 4000);
                                }
                            }
                        });
                    }
                    seconds = 0;
                    tickLabel();
                } catch (e) { /* network hiccup — the next tick retries */ }
            }
        }
        setInterval(poll, 15000);

        // Filter changes restart the feed — reset the cursor.
        document.querySelector('form').addEventListener('submit', () => { lastId = 0; });
    })();
</script>
@endsection
