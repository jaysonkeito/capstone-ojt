@extends('layouts.app')

@section('title', 'My Profile')

@section('content')
<div>
    <div class="mb-8">
        <h1 class="text-xl font-semibold tracking-tight text-gray-900">My Profile</h1>
        <p class="text-sm text-gray-500 mt-0.5">Your photo and details — the admin, your supervisor, and your coordinator see these.</p>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-5 gap-6 items-start">
        {{-- Identity + password --}}
        <div class="lg:col-span-2 space-y-6">
            <div class="bg-white border border-gray-200 rounded-xl p-6">
                <div class="flex items-center gap-4">
                    @include('partials.avatar', ['user' => $user, 'class' => 'w-16 h-16 text-xl'])
                    <div class="min-w-0">
                        <p class="text-sm font-semibold text-gray-900 truncate">{{ $user->full_name_with_middle_initial }}</p>
                        <p class="text-xs text-gray-400 mt-0.5">{{ $user->role_label }}@if($user->isIntern()) · {{ $user->student_id }}@endif
                        @if($user->office) · {{ $user->office->name }}@endif</p>
                        <p class="text-xs text-gray-400 mt-0.5 break-all">{{ $user->email }}</p>
                    </div>
                </div>
                <dl class="mt-5 pt-5 border-t border-gray-100 space-y-3">
                    <div class="flex justify-between items-baseline gap-3">
                        <dt class="text-[11px] font-medium uppercase tracking-widest text-gray-400 shrink-0">Member Since</dt>
                        <dd class="text-sm text-gray-900 tabular-nums">{{ $user->created_at?->format('M j, Y') ?? '—' }}</dd>
                    </div>
                    <div class="flex justify-between items-baseline gap-3">
                        <dt class="text-[11px] font-medium uppercase tracking-widest text-gray-400 shrink-0">Password</dt>
                        <dd class="text-sm {{ $user->password_changed_at ? 'text-gray-900' : 'text-amber-700' }} tabular-nums">
                            {{ $user->password_changed_at ? 'Changed '.$user->password_changed_at->format('M j, Y') : 'Default — change it below' }}
                        </dd>
                    </div>
                </dl>
                @if($user->avatar_path)
                    <form method="POST" action="{{ route('profile.avatar.destroy') }}" class="mt-4 pt-4 border-t border-gray-100" onsubmit="return confirm('Remove your profile photo?');">
                        @csrf
                        @method('DELETE')
                        <button type="submit" class="text-xs font-medium text-red-500 hover:underline">Remove photo</button>
                    </form>
                @endif
            </div>

            <div class="bg-white border border-gray-200 rounded-xl p-6">
                <h2 class="text-sm font-semibold text-gray-900 mb-1">Change Password</h2>
                <p class="text-xs text-gray-400 mb-4">
                    {{ $user->password_changed_at === null
                        ? 'You are still on the default password — pick your own so nobody else can log in as you.'
                        : 'Pick something you don\'t use anywhere else.' }}
                    Shown as text so you can verify what you type.
                </p>

                <form method="POST" action="{{ route('password.update') }}" class="space-y-4">
                    @csrf
                    @method('PUT')

                    <div>
                        <label for="current_password" class="block text-xs font-medium text-gray-600 mb-1">Current Password</label>
                        <input type="text" id="current_password" autocomplete="off" name="current_password" required
                            class="w-full px-3 py-2 rounded-lg border-gray-200 text-sm focus:border-brand-400 focus:ring-2 focus:ring-brand-500/15 transition">
                        @error('current_password') <p class="text-xs text-red-600 mt-1">{{ $message }}</p> @enderror
                    </div>

                    <div>
                        <label for="password" class="block text-xs font-medium text-gray-600 mb-1">New Password</label>
                        <input type="text" id="password" autocomplete="off" name="password" required
                            class="w-full px-3 py-2 rounded-lg border-gray-200 text-sm focus:border-brand-400 focus:ring-2 focus:ring-brand-500/15 transition">
                        <p class="text-[11px] text-gray-400 mt-1">At least 8 characters, with letters and numbers.</p>
                        @error('password') <p class="text-xs text-red-600 mt-1">{{ $message }}</p> @enderror
                    </div>

                    <div>
                        <label for="password_confirmation" class="block text-xs font-medium text-gray-600 mb-1">Confirm New Password</label>
                        <input type="text" id="password_confirmation" autocomplete="off" name="password_confirmation" required
                            class="w-full px-3 py-2 rounded-lg border-gray-200 text-sm focus:border-brand-400 focus:ring-2 focus:ring-brand-500/15 transition">
                    </div>

                    <button type="submit" class="w-full bg-brand-600 hover:bg-brand-700 text-white text-sm font-medium py-2 rounded-lg transition">Update Password</button>
                </form>
            </div>
        </div>

        {{-- Profile details --}}
        <div class="lg:col-span-3">
            <div class="bg-white border border-gray-200 rounded-xl p-6">
                <h2 class="text-sm font-semibold text-gray-900 mb-5">Profile Details</h2>
                <form method="POST" action="{{ route('profile.update') }}" enctype="multipart/form-data" class="space-y-5">
                    @csrf
                    @method('PUT')

                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                        @if($user->isIntern())
                            {{-- Intern names mirror the Registrar's roster — admin-managed --}}
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1.5">First Name</label>
                                <input type="text" value="{{ $user->first_name }}" disabled
                                    class="w-full px-3 py-2 rounded-lg border-gray-100 bg-gray-50 text-sm text-gray-400">
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1.5">Middle Name</label>
                                <input type="text" value="{{ $user->middle_name }}" disabled
                                    class="w-full px-3 py-2 rounded-lg border-gray-100 bg-gray-50 text-sm text-gray-400">
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1.5">Last Name</label>
                                <input type="text" value="{{ $user->last_name }}" disabled
                                    class="w-full px-3 py-2 rounded-lg border-gray-100 bg-gray-50 text-sm text-gray-400">
                                <p class="text-[11px] text-gray-400 mt-1">Names come from the Registrar's roster; the middle name is from the Personal Info sheet — contact the admin for corrections.</p>
                            </div>
                        @else
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1.5">First Name</label>
                                <input type="text" name="first_name" value="{{ old('first_name', $user->first_name) }}" required
                                    class="w-full px-3 py-2 rounded-lg border-gray-200 text-sm focus:border-brand-400 focus:ring-2 focus:ring-brand-500/15 transition">
                                @error('first_name') <p class="text-xs text-red-600 mt-1">{{ $message }}</p> @enderror
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1.5">Last Name</label>
                                <input type="text" name="last_name" value="{{ old('last_name', $user->last_name) }}" required
                                    class="w-full px-3 py-2 rounded-lg border-gray-200 text-sm focus:border-brand-400 focus:ring-2 focus:ring-brand-500/15 transition">
                                @error('last_name') <p class="text-xs text-red-600 mt-1">{{ $message }}</p> @enderror
                            </div>
                        @endif
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1.5">Email</label>
                        <input type="email" name="email" value="{{ old('email', $user->email) }}" required
                            class="w-full px-3 py-2 rounded-lg border-gray-200 text-sm focus:border-brand-400 focus:ring-2 focus:ring-brand-500/15 transition">
                        @error('email') <p class="text-xs text-red-600 mt-1">{{ $message }}</p> @enderror
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1.5">Profile Photo <span class="text-gray-400 font-normal text-xs">(JPG/PNG, max 2MB)</span></label>
                        <input type="file" name="avatar" accept="image/*"
                            class="block w-full text-sm text-gray-500 file:mr-3 file:py-2 file:px-4 file:rounded-lg file:border-0 file:bg-gray-900 file:text-white file:text-xs file:font-medium hover:file:bg-gray-800 file:cursor-pointer">
                        @error('avatar') <p class="text-xs text-red-600 mt-1">{{ $message }}</p> @enderror
                    </div>

                    <button type="submit" class="bg-brand-600 hover:bg-brand-700 text-white text-sm font-medium px-4 py-2 rounded-lg transition">Save Profile</button>
                </form>

                @if($user->isIntern())
                    {{-- The account page holds only sign-in identity; the data
                         behind the school's Personal Information sheet lives on
                         the intern's Personal Info page. --}}
                    <div class="mt-6 rounded-lg bg-brand-50 border border-brand-100 px-4 py-3 flex items-start gap-2.5">
                        <svg class="shrink-0 mt-0.5 text-brand-600" width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M12 8h.01"/><path d="M12 12v4"/><circle cx="12" cy="12" r="9"/></svg>
                        <p class="text-xs text-gray-600 leading-relaxed">
                            This page is your sign-in identity only. Your birth details, addresses, family background, and
                            emergency contact — everything printed on the
                            <span class="font-medium text-gray-700">Student Intern's Personal Information</span> sheet — are
                            maintained under <a href="{{ route('intern.personal-information.edit') }}" class="font-medium text-brand-600 hover:underline">Personal Info</a>.
                        </p>
                    </div>
                @endif
            </div>
        </div>
    </div>
</div>
@endsection
