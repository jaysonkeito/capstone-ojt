@extends('layouts.app')

@section('title', $office->exists ? 'Edit Office' : 'Add Office')

@section('content')
<div>
    <div class="mb-6">
        <a href="{{ route('admin.offices.index') }}" class="text-xs font-medium text-gray-400 hover:text-gray-700">← Offices</a>
        <h1 class="text-xl font-semibold tracking-tight text-gray-900 mt-1">{{ $office->exists ? 'Edit Office' : 'Add Office' }}</h1>
    </div>

    <div class="bg-white border border-gray-200 rounded-xl p-6 sm:p-8">
        <form method="POST" action="{{ $office->exists ? route('admin.offices.update', $office) : route('admin.offices.store') }}" class="space-y-5">
            @csrf
            @if($office->exists) @method('PUT') @endif

            <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
                <div class="sm:col-span-2">
                    <label class="block text-sm font-medium text-gray-700 mb-1.5">Office Name</label>
                    <input type="text" name="name" value="{{ old('name', $office->name) }}" required placeholder="e.g. MIS Office, City Mayor's Office"
                        class="w-full px-3 py-2 rounded-lg border-gray-200 text-sm placeholder:text-gray-300 focus:border-brand-400 focus:ring-2 focus:ring-brand-500/15 transition">
                    @error('name') <p class="text-xs text-red-600 mt-1">{{ $message }}</p> @enderror
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1.5">Type</label>
                    <select name="type" required class="w-full px-3 py-2 rounded-lg border-gray-200 text-sm focus:border-brand-400 focus:ring-2 focus:ring-brand-500/15 transition">
                        <option value="internal" {{ old('type', $office->type ?? 'internal') === 'internal' ? 'selected' : '' }}>Internal (on-campus)</option>
                        <option value="external" {{ old('type', $office->type) === 'external' ? 'selected' : '' }}>External</option>
                    </select>
                </div>
            </div>

            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1.5">Address <span class="text-gray-400 font-normal text-xs">(optional)</span></label>
                <input type="text" name="address" value="{{ old('address', $office->address) }}" placeholder="Building / street / city"
                    class="w-full px-3 py-2 rounded-lg border-gray-200 text-sm placeholder:text-gray-300 focus:border-brand-400 focus:ring-2 focus:ring-brand-500/15 transition">
            </div>

    <div>
        <label class="block text-xs font-medium text-gray-500 mb-1.5">Agency (parent institution / company)</label>
        <input type="text" name="agency" value="{{ old('agency', $office->agency) }}"
            class="w-full px-3 py-2 rounded-lg border-gray-200 text-sm focus:border-brand-400 focus:ring-2 focus:ring-brand-500/15 transition">
    </div>
    <div>
        <label class="block text-xs font-medium text-gray-500 mb-1.5">City</label>
        <input type="text" name="city" value="{{ old('city', $office->city) }}"
            class="w-full px-3 py-2 rounded-lg border-gray-200 text-sm focus:border-brand-400 focus:ring-2 focus:ring-brand-500/15 transition">
    </div>
    <div>
        <label class="block text-xs font-medium text-gray-500 mb-1.5">Province</label>
        <input type="text" name="province" value="{{ old('province', $office->province) }}"
            class="w-full px-3 py-2 rounded-lg border-gray-200 text-sm focus:border-brand-400 focus:ring-2 focus:ring-brand-500/15 transition">
    </div>
    <div>
        <label class="block text-xs font-medium text-gray-500 mb-1.5">Postal Code</label>
        <input type="text" name="postal" value="{{ old('postal', $office->postal) }}"
            class="w-full px-3 py-2 rounded-lg border-gray-200 text-sm focus:border-brand-400 focus:ring-2 focus:ring-brand-500/15 transition">
    </div>

            <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1.5">Contact Person</label>
                    <input type="text" name="contact_person" value="{{ old('contact_person', $office->contact_person) }}"
                        class="w-full px-3 py-2 rounded-lg border-gray-200 text-sm placeholder:text-gray-300 focus:border-brand-400 focus:ring-2 focus:ring-brand-500/15 transition">
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1.5">Contact Email</label>
                    <input type="email" name="contact_email" value="{{ old('contact_email', $office->contact_email) }}"
                        class="w-full px-3 py-2 rounded-lg border-gray-200 text-sm placeholder:text-gray-300 focus:border-brand-400 focus:ring-2 focus:ring-brand-500/15 transition">
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1.5">Contact Phone</label>
                    <input type="text" name="contact_phone" value="{{ old('contact_phone', $office->contact_phone) }}"
                        class="w-full px-3 py-2 rounded-lg border-gray-200 text-sm placeholder:text-gray-300 focus:border-brand-400 focus:ring-2 focus:ring-brand-500/15 transition">
                </div>
            </div>

            {{-- Per-office working times: null columns mean the campus-wide
                 standard from Settings applies; set these only when this
                 office runs a different schedule. --}}
            <div class="border-t border-gray-100 pt-5">
                <label class="block text-sm font-medium text-gray-700">Working Times <span class="text-gray-400 font-normal text-xs">(optional)</span></label>
                <p class="text-[11px] text-gray-400 mt-0.5 mb-3">Leave blank to use the campus-wide times from Settings. Set them only if this office runs a different schedule — interns and the desk scanner here follow these instead.</p>
                <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
                    <div>
                        <label class="block text-xs font-medium text-gray-500 mb-1.5">AM Time In</label>
                        <input type="time" name="am_time_in" value="{{ old('am_time_in', $office->am_time_in ? substr($office->am_time_in, 0, 5) : null) }}"
                            class="w-full px-3 py-2 rounded-lg border-gray-200 text-sm focus:border-brand-400 focus:ring-2 focus:ring-brand-500/15 transition">
                    </div>
                    <div>
                        <label class="block text-xs font-medium text-gray-500 mb-1.5">AM Time Out</label>
                        <input type="time" name="am_time_out" value="{{ old('am_time_out', $office->am_time_out ? substr($office->am_time_out, 0, 5) : null) }}"
                            class="w-full px-3 py-2 rounded-lg border-gray-200 text-sm focus:border-brand-400 focus:ring-2 focus:ring-brand-500/15 transition">
                    </div>
                    <div>
                        <label class="block text-xs font-medium text-gray-500 mb-1.5">PM Time In</label>
                        <input type="time" name="pm_time_in" value="{{ old('pm_time_in', $office->pm_time_in ? substr($office->pm_time_in, 0, 5) : null) }}"
                            class="w-full px-3 py-2 rounded-lg border-gray-200 text-sm focus:border-brand-400 focus:ring-2 focus:ring-brand-500/15 transition">
                    </div>
                    <div>
                        <label class="block text-xs font-medium text-gray-500 mb-1.5">PM Time Out</label>
                        <input type="time" name="pm_time_out" value="{{ old('pm_time_out', $office->pm_time_out ? substr($office->pm_time_out, 0, 5) : null) }}"
                            class="w-full px-3 py-2 rounded-lg border-gray-200 text-sm focus:border-brand-400 focus:ring-2 focus:ring-brand-500/15 transition">
                    </div>
                </div>
            </div>

            <div class="flex gap-2 pt-2">
                <button type="submit" class="bg-brand-600 hover:bg-brand-700 text-white text-sm font-medium px-4 py-2 rounded-lg transition">{{ $office->exists ? 'Save Changes' : 'Create Office' }}</button>
                <a href="{{ route('admin.offices.index') }}" class="text-sm font-medium px-4 py-2 rounded-lg text-gray-500 hover:bg-gray-100 transition">Cancel</a>
            </div>
        </form>
    </div>
</div>
@endsection
