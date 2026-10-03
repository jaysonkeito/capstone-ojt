{{-- Round avatar with initials fallback — expects $user and optional $class (extra classes). --}}
@if($user->avatar_path)
    <img src="{{ $user->avatar_url }}" alt="{{ $user->full_name }}"
         class="{{ $class ?? 'w-8 h-8' }} rounded-full object-cover border border-gray-200 shrink-0">
@else
    <span class="{{ $class ?? 'w-8 h-8' }} rounded-full bg-gray-100 border border-gray-200 flex items-center justify-center text-[11px] font-semibold text-gray-500 shrink-0 select-none">
        {{ $user->initials }}
    </span>
@endif
