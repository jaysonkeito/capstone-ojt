@extends('layouts.app')

@section('title', 'Announcements')

@section('content')
<div class="max-w-3xl">
    <div class="mb-8">
        <h1 class="text-xl font-semibold tracking-tight text-gray-900">Announcements</h1>
        <p class="text-sm text-gray-500 mt-0.5">Posts reach {{ $audienceLabel }} — on their dashboard banner and in their notification inbox.</p>
    </div>

    {{-- Composer --}}
    <div class="bg-white border border-gray-200 rounded-xl p-5 mb-8">
        <h2 class="text-sm font-semibold text-gray-900 mb-4">New announcement</h2>
        <form method="POST" action="{{ route('admin.announcements.store') }}" class="space-y-4">
            @csrf
            <div>
                <label for="title" class="block text-sm font-medium text-gray-700 mb-1.5">Title</label>
                <input type="text" name="title" id="title" required maxlength="150" value="{{ old('title') }}" placeholder="e.g. No duty on Monday — campus holiday"
                    class="w-full px-3 py-2 rounded-lg border-gray-200 text-sm placeholder:text-gray-300 focus:border-brand-400 focus:ring-2 focus:ring-brand-500/15 transition">
                @error('title') <p class="text-xs text-red-600 mt-1">{{ $message }}</p> @enderror
            </div>
            <div>
                <label for="body" class="block text-sm font-medium text-gray-700 mb-1.5">Message</label>
                <textarea name="body" id="body" rows="4" required maxlength="2000" placeholder="What should the interns know?"
                    class="w-full px-3 py-2 rounded-lg border-gray-200 text-sm placeholder:text-gray-300 focus:border-brand-400 focus:ring-2 focus:ring-brand-500/15 transition">{{ old('body') }}</textarea>
                @error('body') <p class="text-xs text-red-600 mt-1">{{ $message }}</p> @enderror
            </div>
            <div class="flex justify-end">
                <button type="submit" class="bg-brand-600 hover:bg-brand-700 text-white text-sm font-medium px-4 py-2 rounded-lg transition">Publish</button>
            </div>
        </form>
    </div>

    {{-- History --}}
    <div class="bg-white border border-gray-200 rounded-xl divide-y divide-gray-100">
        @forelse($announcements as $announcement)
            <div class="px-5 py-4">
                <div class="flex items-start justify-between gap-3">
                    <div class="min-w-0">
                        <p class="text-sm font-medium text-gray-900">{{ $announcement->title }}</p>
                        <p class="text-sm text-gray-600 mt-1 leading-relaxed whitespace-pre-line">{{ $announcement->body }}</p>
                        <p class="text-[11px] text-gray-400 mt-2">
                            {{ $announcement->created_at->format('M d, Y g:i A') }}
                            · {{ \App\Models\Announcement::AUDIENCE_LABELS[$announcement->audience] }}
                            @if(! $announcement->author->is(auth()->user())) · by {{ $announcement->author->full_name }} @endif
                        </p>
                    </div>
                    <form method="POST" action="{{ route('admin.announcements.destroy', $announcement) }}"
                        data-confirm-title="Delete announcement"
                        data-confirm-message="Delete “{{ $announcement->title }}”? Interns who already saw it keep their notification."
                        data-confirm-action="Delete"
                        onsubmit="return askConfirm(this);">
                        @csrf
                        @method('DELETE')
                        <button type="submit" class="shrink-0 w-8 h-8 inline-flex items-center justify-center rounded-md text-gray-400 hover:text-red-600 hover:bg-red-50 transition" title="Delete announcement">
                            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M3 6h18"/><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6"/><path d="M8 6V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"/></svg>
                        </button>
                    </form>
                </div>
            </div>
        @empty
            <p class="px-5 py-14 text-center text-sm text-gray-400">No announcements yet — publish one above.</p>
        @endforelse
    </div>
</div>

@include('partials.confirm-modal')
@endsection
