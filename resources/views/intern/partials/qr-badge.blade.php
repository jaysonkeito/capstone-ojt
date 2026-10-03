{{-- The intern's personal QR ID badge — the on-screen preview on the My QR
     page, mirrored pixel-for-pixel by the downloadable PNG. Portrait,
     lanyard-sized. Expects $intern and $qrDataUri. --}}
<div class="badge w-[18rem] rounded-2xl overflow-hidden bg-white border border-gray-200 shadow-sm">
    <div class="bg-gray-900 text-white text-center px-4 py-3">
        <p class="text-[8px] uppercase tracking-[0.2em] text-gray-400">NORSU CAS — OJT Tracker</p>
        <p class="text-sm font-semibold tracking-tight mt-0.5">Time In / Out</p>
    </div>

    <div class="px-4 pt-4 pb-5 flex flex-col items-center text-center">
        @include('partials.avatar', ['user' => $intern, 'class' => 'w-16 h-16'])

        <p class="mt-2.5 font-semibold text-gray-900 text-sm leading-tight">{{ $intern->full_name }}</p>
        @if($intern->student_id)
            <p class="text-[11px] text-gray-500">{{ $intern->student_id }}</p>
        @endif

        <div class="mt-3 rounded-xl border border-gray-100 p-2 bg-white">
            <img src="{{ $qrDataUri }}" alt="Personal time-in/out QR code" class="w-52 h-52 block">
        </div>

        <p class="mt-3 text-[9px] uppercase tracking-widest text-gray-400">Present at the office scanner</p>
    </div>
</div>
