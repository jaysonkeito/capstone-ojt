@extends('layouts.app')

@section('title', 'Offices')

@section('content')
<div class="flex flex-wrap items-center justify-between gap-3 mb-6">
    <div>
        <h1 class="text-xl font-semibold tracking-tight text-gray-900">Offices</h1>
        <p class="text-sm text-gray-500 mt-0.5">Internal (on-campus) and external offices where interns are placed.</p>
    </div>
    <button type="button" onclick="document.getElementById('addOfficeModal').classList.remove('hidden')"
        class="inline-flex items-center gap-1.5 bg-brand-600 hover:bg-brand-700 text-white text-sm font-medium px-3.5 py-2 rounded-lg transition">+ Add Office</button>
</div>

<div class="bg-white border border-gray-200 rounded-xl overflow-x-auto">
    <table class="w-full text-sm">
        <thead>
            <tr class="text-left text-[11px] font-medium uppercase tracking-widest text-gray-400 border-b border-gray-200">
                <th class="px-5 py-3">Office</th>
                <th class="px-4 py-3">Type</th>
                <th class="px-4 py-3">Contact</th>
                <th class="px-4 py-3 text-center">Interns</th>
                <th class="px-4 py-3 text-center">Supervisors</th>
                <th class="px-5 py-3 text-right">Actions</th>
            </tr>
        </thead>
        <tbody class="divide-y divide-gray-100">
            @forelse($offices as $office)
                <tr class="hover:bg-gray-50/60 transition-colors">
                    <td class="px-5 py-3">
                        <p class="font-medium text-gray-900">{{ $office->name }}</p>
                        @if($office->address)
                            <p class="text-xs text-gray-400">{{ $office->address }}</p>
                        @endif
                    </td>
                    <td class="px-4 py-3">
                        <span class="inline-flex items-center px-2 py-0.5 rounded-md text-xs font-medium {{ $office->type === 'external' ? 'bg-amber-50 text-amber-700' : 'bg-gray-100 text-gray-600' }}">
                            {{ $office->type_label }}
                        </span>
                    </td>
                    <td class="px-4 py-3 text-gray-500 text-xs">
                        @if($office->contact_person)
                            {{ $office->contact_person }}<br>
                            <span class="text-gray-400">{{ $office->contact_email }}{{ $office->contact_phone ? ' · '.$office->contact_phone : '' }}</span>
                        @else
                            <span class="text-gray-300">—</span>
                        @endif
                    </td>
                    <td class="px-4 py-3 text-center text-gray-700 tabular-nums">{{ $office->interns_count }}</td>
                    <td class="px-4 py-3 text-center text-gray-700 tabular-nums">{{ $office->supervisors_count }}</td>
                    <td class="px-5 py-3 text-right whitespace-nowrap">
                        <a href="{{ route('admin.offices.edit', $office) }}" class="text-xs font-medium text-brand-600 hover:underline">Edit</a>
                        <form method="POST" action="{{ route('admin.offices.destroy', $office) }}" class="inline"
                              data-confirm-title="Delete office"
                              data-confirm-message="Delete {{ $office->name }}? Interns and supervisors placed here will become unassigned."
                              data-confirm-action="Delete"
                              onsubmit="return askConfirm(this);">
                            @csrf
                            @method('DELETE')
                            <button class="text-xs font-medium text-red-500 hover:underline ml-3">Delete</button>
                        </form>
                    </td>
                </tr>
            @empty
                <tr><td colspan="6" class="px-5 py-10 text-center text-gray-400 text-sm">No offices yet — add the first one.</td></tr>
            @endforelse
        </tbody>
    </table>
</div>

{{-- Add Office Modal --}}
<div id="addOfficeModal" class="hidden fixed inset-0 z-50 flex items-start justify-center bg-gray-900/25 px-4 pt-[8vh] overflow-y-auto"
     onclick="if(event.target === this) this.classList.add('hidden')">
    <div class="bg-white border border-gray-200 rounded-xl shadow-xl w-full max-w-md p-6 mb-10">
        <div class="flex items-center justify-between mb-5">
            <h3 class="font-semibold text-gray-900">Add Office</h3>
            <button type="button" onclick="document.getElementById('addOfficeModal').classList.add('hidden')" class="text-gray-400 hover:text-gray-900">
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M18 6 6 18M6 6l12 12"/></svg>
            </button>
        </div>
        <form method="POST" action="{{ route('admin.offices.store') }}" class="space-y-4">
            @csrf
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <div>
                    <label class="block text-xs font-medium text-gray-600 mb-1">Office Name</label>
                    <input type="text" name="name" value="{{ old('name') }}" required placeholder="e.g. MIS Office, City Mayor's Office"
                        class="w-full px-3 py-2 rounded-lg border-gray-200 text-sm placeholder:text-gray-300 focus:border-brand-400 focus:ring-2 focus:ring-brand-500/15 transition">
                    @error('name') <p class="text-xs text-red-600 mt-1">{{ $message }}</p> @enderror
                </div>
                <div>
                    <label class="block text-xs font-medium text-gray-600 mb-1">Type</label>
                    <select name="type" required class="w-full px-3 py-2 rounded-lg border-gray-200 text-sm focus:border-brand-400 focus:ring-2 focus:ring-brand-500/15 transition">
                        <option value="internal">Internal (on-campus)</option>
                        <option value="external">External</option>
                    </select>
                </div>
            </div>
            <div>
                <label class="block text-xs font-medium text-gray-600 mb-1">Address <span class="text-gray-400 font-normal">(optional)</span></label>
                <input type="text" name="address" value="{{ old('address') }}" placeholder="Building / street / city"
                    class="w-full px-3 py-2 rounded-lg border-gray-200 text-sm placeholder:text-gray-300 focus:border-brand-400 focus:ring-2 focus:ring-brand-500/15 transition">
            </div>
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <div>
                    <label class="block text-xs font-medium text-gray-600 mb-1">Contact Person</label>
                    <input type="text" name="contact_person" value="{{ old('contact_person') }}"
                        class="w-full px-3 py-2 rounded-lg border-gray-200 text-sm placeholder:text-gray-300 focus:border-brand-400 focus:ring-2 focus:ring-brand-500/15 transition">
                </div>
                <div>
                    <label class="block text-xs font-medium text-gray-600 mb-1">Contact Email</label>
                    <input type="email" name="contact_email" value="{{ old('contact_email') }}"
                        class="w-full px-3 py-2 rounded-lg border-gray-200 text-sm placeholder:text-gray-300 focus:border-brand-400 focus:ring-2 focus:ring-brand-500/15 transition">
                </div>
            </div>
            <div>
                <label class="block text-xs font-medium text-gray-600 mb-1">Contact Phone</label>
                <input type="text" name="contact_phone" value="{{ old('contact_phone') }}"
                    class="w-full px-3 py-2 rounded-lg border-gray-200 text-sm placeholder:text-gray-300 focus:border-brand-400 focus:ring-2 focus:ring-brand-500/15 transition">
            </div>
            <div class="flex gap-2 pt-2">
                <button type="submit" class="bg-brand-600 hover:bg-brand-700 text-white text-sm font-medium px-4 py-2 rounded-lg transition">Create Office</button>
                <button type="button" onclick="document.getElementById('addOfficeModal').classList.add('hidden')" class="text-sm font-medium px-4 py-2 rounded-lg text-gray-500 hover:bg-gray-100 transition">Cancel</button>
            </div>
        </form>
    </div>
</div>

@include('partials.confirm-modal')
@endsection
