@extends('layouts.app')

@section('title', 'Scan Captures')

@section('content')
<div class="mb-8">
    <h1 class="text-xl font-semibold tracking-tight text-gray-900">Scan Captures</h1>
    <p class="text-sm text-gray-500 mt-0.5">Webcam snapshots the kiosk took at each successful scan — verify the person behind every time in/out. Most recent scan first.</p>
</div>

{{-- Toolbar: date navigator + intern search --}}
<div class="flex flex-wrap items-center justify-between gap-3 mb-5">
    <div class="flex flex-wrap items-center gap-1.5">
        <a href="{{ route('admin.kiosk-captures.index', ['date' => $date->copy()->subDay()->format('Y-m-d'), 'q' => $search]) }}"
            class="w-8 h-8 rounded-lg border border-gray-200 bg-white flex items-center justify-center hover:bg-gray-50 text-gray-500 transition" title="Previous day">
            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M15 18l-6-6 6-6"/></svg>
        </a>
        <div class="px-3.5 py-1.5 rounded-lg bg-white border border-gray-200">
            <span class="text-sm font-medium text-gray-900">{{ $date->format('l, F j, Y') }}</span>
        </div>
        @unless($date->isToday())
            <a href="{{ $date->copy()->addDay()->isAfter(today()) ? route('admin.kiosk-captures.index', ['q' => $search]) : route('admin.kiosk-captures.index', ['date' => $date->copy()->addDay()->format('Y-m-d'), 'q' => $search]) }}"
                class="w-8 h-8 rounded-lg border border-gray-200 bg-white flex items-center justify-center hover:bg-gray-50 text-gray-500 transition" title="Next day">
                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M9 18l6-6-6-6"/></svg>
            </a>
        @endunless
        @unless($date->isToday())
            <a href="{{ route('admin.kiosk-captures.index', ['q' => $search]) }}" class="text-sm font-medium text-brand-600 hover:underline ml-1.5">Today</a>
        @endunless
    </div>

    <form method="GET" action="{{ route('admin.kiosk-captures.index') }}" class="flex items-center gap-1.5">
        <input type="hidden" name="date" value="{{ $date->format('Y-m-d') }}">
        <input type="search" name="q" value="{{ $search }}" placeholder="Search name or Student ID"
            class="px-3 py-1.5 rounded-lg border-gray-200 text-xs text-gray-700 placeholder:text-gray-400 focus:border-brand-400 focus:ring-2 focus:ring-brand-500/15 transition">
        <button type="submit" class="inline-flex items-center gap-1.5 bg-white border border-gray-200 hover:bg-gray-50 text-gray-700 text-xs font-medium px-2.5 py-1.5 rounded-lg transition">Search</button>
    </form>
</div>

@if($logs->isEmpty())
    <div class="bg-white border border-gray-200 rounded-xl px-6 py-14 text-center">
        <div class="w-12 h-12 rounded-2xl bg-gray-50 border border-gray-100 flex items-center justify-center mx-auto mb-4">
            <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" class="text-gray-300"><path d="M14.5 4h-5L7 7H4a2 2 0 0 0-2 2v9a2 2 0 0 0 2 2h16a2 2 0 0 0 2-2V9a2 2 0 0 0-2-2h-3l-2.5-3z"/><circle cx="12" cy="13" r="3"/></svg>
        </div>
        <p class="text-sm font-medium text-gray-700">No scan captures for this date</p>
        <p class="text-xs text-gray-400 mt-1">Either no one scanned at the kiosk that day, or the station's camera was off or denied.</p>
    </div>
@else
    <div class="grid grid-cols-2 md:grid-cols-3 xl:grid-cols-4 gap-4">
        @foreach($logs as $log)
            @foreach($log->kiosk_captures ?? [] as $slot => $path)
                @php
                    $time = $log->{$slot} ? \Illuminate\Support\Carbon::parse($log->{$slot})->format('g:i A') : '—';
                @endphp
                <div class="bg-white border border-gray-200 rounded-xl overflow-hidden hover:shadow-md transition-shadow">
                    <button type="button" class="block w-full relative cursor-zoom-in"
                        onclick="openCapture('{{ \Illuminate\Support\Facades\Storage::disk('public')->url($path) }}')">
                        <img src="{{ \Illuminate\Support\Facades\Storage::disk('public')->url($path) }}" loading="lazy"
                            alt="Kiosk capture — {{ $log->user->full_name }} at {{ $time }}"
                            class="w-full aspect-[4/3] object-cover bg-gray-100">
                        <span class="absolute top-2 left-2 inline-flex items-center px-2 py-0.5 rounded-md text-[10px] font-semibold bg-gray-900/70 text-white">
                            {{ \App\Support\AttendanceRecorder::labelFor($slot) }}
                        </span>
                    </button>
                    <div class="px-3 py-2.5">
                        <p class="text-sm font-medium text-gray-900 truncate" title="{{ $log->user->full_name }}">{{ $log->user->full_name }}</p>
                        <p class="text-xs text-gray-500 mt-0.5 flex items-center justify-between gap-2">
                            <span class="tabular-nums">{{ $time }}{{ $log->user->student_id ? ' · '.$log->user->student_id : '' }}</span>
                            @if($log->user->office)
                                <span class="truncate max-w-[45%] text-right text-gray-400" title="{{ $log->user->office->name }}">{{ $log->user->office->name }}</span>
                            @endif
                        </p>
                    </div>
                </div>
            @endforeach
        @endforeach
    </div>

    <div class="mt-5">{{ $logs->links() }}</div>
@endif

{{-- Click-to-zoom lightbox --}}
<div id="captureZoom" class="hidden fixed inset-0 z-50 flex items-center justify-center p-4"
    onclick="if(event.target === this) this.classList.add('hidden')">
    <div class="absolute inset-0 bg-gray-900/60" onclick="document.getElementById('captureZoom').classList.add('hidden')"></div>
    <img id="captureZoomImg" class="relative max-h-[85vh] max-w-full rounded-xl shadow-2xl ring-1 ring-white/20 bg-black" alt="Kiosk capture enlarged">
</div>

<script>
    function openCapture(src) {
        document.getElementById('captureZoomImg').src = src;
        document.getElementById('captureZoom').classList.remove('hidden');
    }
</script>
@endsection
