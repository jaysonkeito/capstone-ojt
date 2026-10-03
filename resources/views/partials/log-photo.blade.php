{{-- Reusable photo cell for log tables — expects $log. Shows the intern's
     duty photo thumbnail (click to open full size), an amber "Awaiting
     photo" badge when today's clocked-out entry still needs its proof
     photo, or a dash. --}}
@if($log->photo_path)
    <a href="{{ $log->photo_url }}" target="_blank" title="View duty photo" class="inline-block">
        <img src="{{ $log->photo_url }}" alt="Duty photo" class="w-9 h-9 object-cover rounded-md border border-gray-200 hover:border-gray-300 transition">
    </a>
@elseif($log->photo_required)
    <span class="inline-flex items-center gap-1.5 text-xs font-medium text-amber-700">
        <span class="w-1.5 h-1.5 rounded-full bg-amber-500"></span>
        Awaiting photo
    </span>
@else
    <span class="text-gray-300">—</span>
@endif
