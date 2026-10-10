@extends('layouts.app')

@section('title', auth()->user()->isCoordinator() ? 'Class' : 'My Class')

@section('content')
<div>
    <div class="mb-8">
        <h1 class="text-xl font-semibold tracking-tight text-gray-900">{{ auth()->user()->isCoordinator() ? 'Class' : 'My Class' }}</h1>
        <p class="text-sm text-gray-500 mt-0.5">
            @if(auth()->user()->isCoordinator())
                The shared board for you and your {{ $classmates->count() }} intern{{ $classmates->count() === 1 ? '' : 's' }} — announcements, reminders, questions.
            @else
                The shared board for your class under {{ $coordinator->full_name }} — everyone in the class sees every message.
            @endif
        </p>
    </div>

    <div class="bg-white border border-gray-200 rounded-xl flex flex-col" style="min-height: 26rem;">
        {{-- The feed --}}
        <div class="flex-1 divide-y divide-gray-100 overflow-y-auto max-h-[32rem]">
            @forelse($messages as $message)
                @php $author = $message->author; @endphp
                <div class="px-5 py-4 flex items-start gap-3">
                    @include('partials.avatar', ['user' => $author, 'class' => 'w-9 h-9 text-sm'])
                    <div class="min-w-0 flex-1">
                        <p class="text-sm">
                            <span class="font-medium text-gray-900">{{ $author->full_name }}</span>
                            <span class="text-[11px] text-gray-400 ml-1.5">{{ $author->role_label }} · {{ $message->created_at->format('M d, g:i A') }}</span>
                        </p>
                        <p class="text-sm text-gray-600 mt-1 leading-relaxed whitespace-pre-line">{{ $message->body }}</p>
                    </div>
                </div>
            @empty
                <p class="px-5 py-16 text-center text-sm text-gray-400">
                    No messages yet — start the conversation{{ auth()->user()->isCoordinator() ? ' with an announcement to your class.' : '.' }}
                </p>
            @endforelse
        </div>

        {{-- Composer --}}
        <form method="POST" action="{{ route('class-board.store') }}" class="border-t border-gray-100 p-4 flex items-start gap-3">
            @csrf
            @include('partials.avatar', ['user' => auth()->user(), 'class' => 'w-9 h-9 text-sm'])
            <div class="flex-1">
                <textarea name="body" rows="2" required maxlength="2000"
                    placeholder="{{ auth()->user()->isCoordinator() ? 'Post to your class…' : 'Message the class…' }}"
                    class="w-full px-3 py-2 rounded-lg border-gray-200 text-sm placeholder:text-gray-300 focus:border-brand-400 focus:ring-2 focus:ring-brand-500/15 transition">{{ old('body') }}</textarea>
                @error('body') <p class="text-xs text-red-600 mt-1">{{ $message }}</p> @enderror
            </div>
            <button type="submit" class="bg-brand-600 hover:bg-brand-700 text-white text-sm font-medium px-4 py-2 rounded-lg transition shrink-0">Post</button>
        </form>
    </div>
</div>

@push('scripts')
<script>
// Land on the newest message when the board opens.
const feed = document.querySelector('.divide-y.overflow-y-auto');
if (feed) { feed.scrollTop = feed.scrollHeight; }
</script>
@endpush
@endsection
