@extends('layouts.app')

@section('title', 'Requirements')

@section('content')
<div class="max-w-3xl mx-auto">
    <div class="mb-8">
        <h1 class="text-xl font-semibold tracking-tight text-gray-900">OJT Requirements</h1>
        <p class="text-sm text-gray-500 mt-0.5">The forms you need for your on-the-job training — each one downloads with your information already filled in.</p>
    </div>

    {{-- How it works --}}
    <div class="mb-8 flex items-start gap-3 rounded-xl border border-brand-100 bg-brand-50/60 p-4 sm:p-5">
        <svg class="mt-0.5 shrink-0 text-brand-600" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="9"/><path d="M12 16v-4M12 8h.01"/></svg>
        <p class="text-sm text-gray-600 leading-relaxed">
            Download any form below. Where your details are on file — your name, course, placement, coordinator — they're merged into the document for you. Anything not yet on file is left blank for you to complete by hand.
        </p>
    </div>

    {{-- The school's requirement forms, in official order --}}
    <div class="bg-white border border-gray-200 rounded-xl divide-y divide-gray-100 overflow-hidden">
        @foreach($requirements as $requirement)
            <div class="flex items-center gap-4 px-5 py-4">
                <span class="shrink-0 w-8 h-8 rounded-lg bg-gray-100 text-gray-500 text-sm font-semibold flex items-center justify-center">
                    {{ $loop->iteration }}
                </span>

                <div class="min-w-0 flex-1">
                    <p class="text-sm font-medium text-gray-900 truncate">{{ $requirement['label'] }}</p>
                    @if($requirement['autofilled'])
                        <span class="mt-1 inline-flex items-center gap-1.5 rounded-full bg-emerald-50 border border-emerald-100 px-2 py-0.5 text-[11px] font-medium text-emerald-700">
                            <span class="w-1.5 h-1.5 rounded-full bg-emerald-500"></span>
                            Filled with your info
                        </span>
                    @else
                        <span class="mt-1 inline-flex items-center gap-1.5 rounded-full bg-gray-100 border border-gray-200 px-2 py-0.5 text-[11px] font-medium text-gray-500">
                            <span class="w-1.5 h-1.5 rounded-full bg-gray-400"></span>
                            Blank official form
                        </span>
                    @endif
                </div>

                <a href="{{ route('intern.requirements.download', $requirement['type']) }}"
                   class="shrink-0 inline-flex items-center gap-1.5 bg-brand-600 hover:bg-brand-700 text-white text-sm font-medium px-4 py-2 rounded-lg transition">
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M12 3v12m0 0 4-4m-4 4-4-4"/><path d="M5 21h14"/></svg>
                    Download
                </a>
            </div>
        @endforeach
    </div>
</div>
@endsection
