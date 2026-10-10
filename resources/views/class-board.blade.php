@extends('layouts.app')

@section('title', auth()->user()->isCoordinator() ? 'Class' : 'My Class')

@section('content')
<div class="max-w-3xl mx-auto">
    {{-- Chat header — who the class belongs to and who is in it --}}
    <div class="flex items-center gap-3 px-1 mb-4">
        @include('partials.avatar', ['user' => $coordinator, 'class' => 'w-10 h-10'])
        <div class="min-w-0 flex-1">
            <h1 class="text-base font-semibold tracking-tight text-gray-900 leading-tight">
                {{ auth()->user()->isCoordinator() ? 'Your class' : $coordinator->full_name."'s class" }}
            </h1>
            <p class="text-xs text-gray-500 mt-0.5">
                {{ auth()->user()->isCoordinator()
                    ? $classmates->count().' intern'.($classmates->count() === 1 ? '' : 's').' · everyone sees every message'
                    : 'Coordinator · everyone in the class sees every message' }}
            </p>
        </div>
    </div>

    {{-- The conversation — Messenger-style: own messages right in brand
         bubbles, everyone else left with their name above a gray bubble. --}}
    <div class="bg-white border border-gray-200 rounded-2xl flex flex-col overflow-hidden" style="height: calc(100vh - 14rem); min-height: 26rem;">
        <div id="chatFeed" class="flex-1 overflow-y-auto px-4 py-5 space-y-2.5">
            @forelse($messages as $message)
                @php $author = $message->author; $mine = $author->id === auth()->id(); @endphp
                <div class="flex {{ $mine ? 'justify-end' : 'justify-start' }}">
                    @if(! $mine)
                        <div class="w-7 mr-2 shrink-0 self-end mb-0.5">
                            @include('partials.avatar', ['user' => $author, 'class' => 'w-7 h-7'])
                        </div>
                    @endif
                    <div class="max-w-[78%] sm:max-w-[70%]">
                        @if(! $mine)
                            <p class="text-[11px] font-medium text-gray-500 mb-0.5 ml-1">{{ $author->full_name }}<span class="text-gray-400 font-normal"> · {{ $author->role_label }}</span></p>
                        @endif
                        <div class="px-3.5 py-2 text-sm leading-relaxed whitespace-pre-line break-words {{ $mine
                            ? 'bg-brand-600 text-white rounded-2xl rounded-br-md'
                            : 'bg-gray-100 text-gray-800 rounded-2xl rounded-bl-md' }}">
                            {{ $message->body }}
                        </div>
                        <p class="text-[10px] text-gray-400 mt-0.5 {{ $mine ? 'text-right mr-1' : 'ml-1' }}">{{ $message->created_at->format('M d, g:i A') }}</p>
                    </div>
                </div>
            @empty
                <div class="h-full flex items-center justify-center py-16">
                    <p class="text-sm text-gray-400">
                        No messages yet — say hello{{ auth()->user()->isCoordinator() ? ' or post an announcement to your class.' : ' to start the conversation.' }}
                    </p>
                </div>
            @endforelse
        </div>

        {{-- Composer — pill input, Enter sends, Shift+Enter makes a line --}}
        <form id="chatForm" method="POST" action="{{ route('class-board.store') }}" class="border-t border-gray-100 p-3">
            @csrf
            <div class="flex items-end gap-2 bg-gray-100 rounded-full px-4 py-1.5">
                <textarea id="chatInput" name="body" rows="1" required maxlength="2000"
                    placeholder="Aa"
                    class="flex-1 resize-none max-h-32 bg-transparent border-0 focus:ring-0 text-sm py-1.5 placeholder:text-gray-400 focus:border-0">{{ old('body') }}</textarea>
                <button type="submit" aria-label="Send"
                    class="my-1 shrink-0 w-8 h-8 rounded-full bg-brand-600 hover:bg-brand-700 text-white flex items-center justify-center transition">
                    <svg width="15" height="15" viewBox="0 0 24 24" fill="currentColor"><path d="M3.4 20.4l17.45-7.48a1 1 0 0 0 0-1.84L3.4 3.6a.993.993 0 0 0-1.39.91L2 9.12c0 .5.37.93.87.99L17 12 2.87 13.88c-.5.07-.87.5-.87 1l.01 4.61c0 .71.73 1.2 1.39.91z"/></svg>
                </button>
            </div>
            @error('body') <p class="text-xs text-red-600 mt-1 px-2">{{ $message }}</p> @enderror
        </form>
    </div>
</div>

@push('scripts')
<script>
(function () {
    const feed = document.getElementById('chatFeed');
    if (feed) { feed.scrollTop = feed.scrollHeight; }

    // Enter sends; Shift+Enter is a new line — the messenger habit.
    const input = document.getElementById('chatInput');
    if (input) {
        input.addEventListener('keydown', function (e) {
            if (e.key === 'Enter' && ! e.shiftKey) {
                e.preventDefault();
                document.getElementById('chatForm').submit();
            }
        });
        // Grow with the message, up to a handful of lines.
        input.addEventListener('input', function () {
            input.style.height = 'auto';
            input.style.height = Math.min(input.scrollHeight, 128) + 'px';
        });
    }
})();
</script>
@endpush
@endsection
