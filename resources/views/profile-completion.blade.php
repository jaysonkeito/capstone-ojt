@extends('layouts.app')

@section('title', 'Complete Your Profile')

@section('content')
@php
    $labelClass = 'block text-sm font-medium text-gray-700 mb-1.5';
    $inputClass = 'w-full px-3 py-2 rounded-lg border-gray-200 text-sm focus:border-brand-400 focus:ring-2 focus:ring-brand-500/15 transition';
    $disabledClass = 'w-full px-3 py-2 rounded-lg border-gray-100 bg-gray-50 text-sm text-gray-400';
    $hintClass = 'text-[11px] text-gray-400 mt-1';
    $isStudent = $user->isIntern();
    $isFirstTime = $user->needsProfileCompletion();
    // Saved value first, falling back to any old() input on a validation bounce.
    $v = fn (string $field, $source) => old($field, $source?->{$field});
    $storedProgram = $user->department === 'BSINT' ? 'BSIT' : $user->department;
    $photo = $user->avatar_url;
@endphp

<div class="max-w-3xl mx-auto">
    <div class="mb-8">
        <h1 class="text-xl font-semibold tracking-tight text-gray-900">
            {{ $isFirstTime ? 'Complete Your Profile' : 'Personal Profile' }}
        </h1>
        <p class="text-sm text-gray-500 mt-0.5">
            @if($isFirstTime)
                @if($isStudent)
                    Tell us a little about yourself so your student record is ready before you start — the dashboard unlocks once you save.
                @else
                    Tell us a little about yourself so your instructor record is ready before you start — the dashboard unlocks once you save.
                @endif
            @else
                Keep your {{ $isStudent ? 'student' : 'instructor' }} details up to date. Changes save straight back to your record.
            @endif
        </p>
    </div>

    @if($isFirstTime)
        <div class="mb-6 flex items-start gap-3 rounded-lg bg-amber-50 border border-amber-100 px-4 py-3 text-amber-800 text-sm">
            <span class="mt-0.5 shrink-0">✋</span>
            <span><span class="font-medium">Almost there.</span> Save this form once and you'll be taken straight to your dashboard.</span>
        </div>
    @endif

    <form method="POST" action="{{ route('profile-completion.update') }}" enctype="multipart/form-data" class="space-y-6">
        @csrf
        @method('PUT')

        {{-- Account details --}}
        <div class="bg-white border border-gray-200 rounded-xl p-6 sm:p-8">
            <h2 class="text-sm font-semibold text-gray-900 mb-5">Account Details</h2>

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <div>
                    <label class="{{ $labelClass }}">Username</label>
                    <input type="text" name="username" value="{{ old('username', $user->username) }}" autocomplete="off"
                        placeholder="e.g. {{ strtolower($user->first_name) }}_{{ strtolower($user->last_name) }}"
                        class="{{ $inputClass }}">
                    @error('username') <p class="text-xs text-red-600 mt-1">{{ $message }}</p> @enderror
                </div>
                <div>
                    <label class="{{ $labelClass }}">Email Address</label>
                    <input type="email" name="email" value="{{ old('email', $user->email) }}" required class="{{ $inputClass }}">
                    @error('email') <p class="text-xs text-red-600 mt-1">{{ $message }}</p> @enderror
                </div>
                @if($isStudent)
                    <div>
                        <label class="{{ $labelClass }}">First Name</label>
                        <input type="text" value="{{ $user->first_name }}" disabled class="{{ $disabledClass }}">
                    </div>
                    <div>
                        <label class="{{ $labelClass }}">Last Name</label>
                        <input type="text" value="{{ $user->last_name }}" disabled class="{{ $disabledClass }}">
                    </div>
                @else
                    <div>
                        <label class="{{ $labelClass }}">First Name</label>
                        <input type="text" name="first_name" value="{{ old('first_name', $user->first_name) }}" required class="{{ $inputClass }}">
                        @error('first_name') <p class="text-xs text-red-600 mt-1">{{ $message }}</p> @enderror
                    </div>
                    <div>
                        <label class="{{ $labelClass }}">Last Name</label>
                        <input type="text" name="last_name" value="{{ old('last_name', $user->last_name) }}" required class="{{ $inputClass }}">
                        @error('last_name') <p class="text-xs text-red-600 mt-1">{{ $message }}</p> @enderror
                    </div>
                    <div>
                        <label class="{{ $labelClass }}">Prefix Title <span class="text-gray-400 font-normal">(optional)</span></label>
                        <input type="text" name="prefix_title" value="{{ $v('prefix_title', $profile) }}" placeholder="Mr., Ms., Dr." class="{{ $inputClass }}">
                        @error('prefix_title') <p class="text-xs text-red-600 mt-1">{{ $message }}</p> @enderror
                    </div>
                    <div>
                        <label class="{{ $labelClass }}">Suffix Title <span class="text-gray-400 font-normal">(optional)</span></label>
                        <input type="text" name="suffix_title" value="{{ $v('suffix_title', $profile) }}" placeholder="Jr., III" class="{{ $inputClass }}">
                        @error('suffix_title') <p class="text-xs text-red-600 mt-1">{{ $message }}</p> @enderror
                    </div>
                @endif
                <div>
                    <label class="{{ $labelClass }}">Middle Name <span class="text-gray-400 font-normal">(optional)</span></label>
                    <input type="text" name="middle_name" value="{{ $v('middle_name', $isStudent ? $info : $profile) }}" class="{{ $inputClass }}">
                    @error('middle_name') <p class="text-xs text-red-600 mt-1">{{ $message }}</p> @enderror
                </div>
            </div>
        </div>

        @if($isStudent)
            {{-- Student information --}}
            <div class="bg-white border border-gray-200 rounded-xl p-6 sm:p-8">
                <h2 class="text-sm font-semibold text-gray-900 mb-1">Student Information</h2>
                <p class="text-xs text-gray-400 mb-5">From the Registrar's roster where shown — contact the admin for corrections.</p>

                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <div>
                        <label class="{{ $labelClass }}">Student ID</label>
                        <input type="text" value="{{ $user->student_id }}" disabled class="{{ $disabledClass }}">
                    </div>
                    <div>
                        <label class="{{ $labelClass }}">Year Level</label>
                        <select name="year_level" class="{{ $inputClass }}">
                            <option value="">Select year level</option>
                            @foreach($yearLevels as $level)
                                <option value="{{ $level }}" @selected((int) old('year_level', $user->year_level) === $level)>
                                    {{ $level }}@if($level === 1)st @elseif($level === 2)nd @elseif($level === 3)rd @else th @endif Year
                                </option>
                            @endforeach
                        </select>
                        @error('year_level') <p class="text-xs text-red-600 mt-1">{{ $message }}</p> @enderror
                    </div>
                    <div>
                        <label class="{{ $labelClass }}">Program</label>
                        <select name="department" class="{{ $inputClass }}">
                            <option value="">Select program</option>
                            @foreach($programs as $code => $full)
                                <option value="{{ $code }}" @selected(old('department', $storedProgram) === $code)>{{ $full }}</option>
                            @endforeach
                        </select>
                        @error('department') <p class="text-xs text-red-600 mt-1">{{ $message }}</p> @enderror
                    </div>
                    <div>
                        <label class="{{ $labelClass }}">Major <span class="text-gray-400 font-normal">(optional)</span></label>
                        <input type="text" name="major" value="{{ $v('major', $info) }}" class="{{ $inputClass }}">
                        @error('major') <p class="text-xs text-red-600 mt-1">{{ $message }}</p> @enderror
                    </div>
                    <div>
                        <label class="{{ $labelClass }}">Birthdate</label>
                        <input type="date" name="birthdate" value="{{ old('birthdate', $info?->birthdate?->format('Y-m-d')) }}" class="{{ $inputClass }}">
                        @error('birthdate') <p class="text-xs text-red-600 mt-1">{{ $message }}</p> @enderror
                    </div>
                    <div>
                        <label class="{{ $labelClass }}">Gender</label>
                        <select name="sex" class="{{ $inputClass }}">
                            <option value="">Select gender</option>
                            @foreach(['Male', 'Female'] as $option)
                                <option value="{{ $option }}" @selected($v('sex', $info) === $option)>{{ $option }}</option>
                            @endforeach
                        </select>
                        @error('sex') <p class="text-xs text-red-600 mt-1">{{ $message }}</p> @enderror
                    </div>
                    <div>
                        <label class="{{ $labelClass }}">Phone Number</label>
                        <input type="tel" name="phone_number" value="{{ $v('phone_number', $info) }}" placeholder="0917 123 4567" class="{{ $inputClass }}">
                        @error('phone_number') <p class="text-xs text-red-600 mt-1">{{ $message }}</p> @enderror
                    </div>
                    <div>
                        <label class="{{ $labelClass }}">Institutional Email <span class="text-gray-400 font-normal">(optional)</span></label>
                        <input type="email" name="institutional_email" value="{{ $v('institutional_email', $info) }}" class="{{ $inputClass }}">
                        <p class="{{ $hintClass }}">Optional. Used for Google Meet participant verification.</p>
                        @error('institutional_email') <p class="text-xs text-red-600 mt-1">{{ $message }}</p> @enderror
                    </div>
                    <div class="sm:col-span-2">
                        <label class="{{ $labelClass }}">Address</label>
                        <input type="text" name="present_address" value="{{ $v('present_address', $info) }}" class="{{ $inputClass }}">
                        @error('present_address') <p class="text-xs text-red-600 mt-1">{{ $message }}</p> @enderror
                    </div>
                    <div>
                        <label class="{{ $labelClass }}">Guardian Name</label>
                        <input type="text" name="guardian_name" value="{{ $v('guardian_name', $info) }}" class="{{ $inputClass }}">
                        @error('guardian_name') <p class="text-xs text-red-600 mt-1">{{ $message }}</p> @enderror
                    </div>
                    <div>
                        <label class="{{ $labelClass }}">Guardian Contact</label>
                        <input type="tel" name="guardian_contact" value="{{ $v('guardian_contact', $info) }}" placeholder="0917 123 4567" class="{{ $inputClass }}">
                        @error('guardian_contact') <p class="text-xs text-red-600 mt-1">{{ $message }}</p> @enderror
                    </div>
                    <div>
                        <label class="{{ $labelClass }}">Facebook Link <span class="text-gray-400 font-normal">(optional)</span></label>
                        <input type="text" name="facebook_link" value="{{ $v('facebook_link', $info) }}" placeholder="facebook.com/yourname" class="{{ $inputClass }}">
                        @error('facebook_link') <p class="text-xs text-red-600 mt-1">{{ $message }}</p> @enderror
                    </div>
                    <div>
                        <label class="{{ $labelClass }}">LinkedIn Link <span class="text-gray-400 font-normal">(optional)</span></label>
                        <input type="text" name="linkedin_link" value="{{ $v('linkedin_link', $info) }}" placeholder="linkedin.com/in/yourname" class="{{ $inputClass }}">
                        @error('linkedin_link') <p class="text-xs text-red-600 mt-1">{{ $message }}</p> @enderror
                    </div>
                    <div class="sm:col-span-2">
                        <label class="{{ $labelClass }}">YouTube Link <span class="text-gray-400 font-normal">(optional)</span></label>
                        <input type="text" name="youtube_link" value="{{ $v('youtube_link', $info) }}" placeholder="youtube.com/@yourname" class="{{ $inputClass }}">
                        @error('youtube_link') <p class="text-xs text-red-600 mt-1">{{ $message }}</p> @enderror
                    </div>
                </div>
            </div>
        @else
            {{-- Instructor information --}}
            <div class="bg-white border border-gray-200 rounded-xl p-6 sm:p-8">
                <h2 class="text-sm font-semibold text-gray-900 mb-5">Instructor Information</h2>

                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <div>
                        <label class="{{ $labelClass }}">Employee ID</label>
                        <input type="text" name="employee_id" value="{{ $v('employee_id', $profile) }}" class="{{ $inputClass }}">
                        @error('employee_id') <p class="text-xs text-red-600 mt-1">{{ $message }}</p> @enderror
                    </div>
                    <div>
                        <label class="{{ $labelClass }}">Designation</label>
                        <input type="text" name="designation" value="{{ $v('designation', $profile) }}" placeholder="e.g. Instructor I" class="{{ $inputClass }}">
                        @error('designation') <p class="text-xs text-red-600 mt-1">{{ $message }}</p> @enderror
                    </div>
                    <div>
                        <label class="{{ $labelClass }}">Department</label>
                        <input type="text" name="department" value="{{ $v('department', $profile) }}" placeholder="e.g. College of Arts and Sciences" class="{{ $inputClass }}">
                        @error('department') <p class="text-xs text-red-600 mt-1">{{ $message }}</p> @enderror
                    </div>
                    <div>
                        <label class="{{ $labelClass }}">Date Hired</label>
                        <input type="date" name="date_hired" value="{{ old('date_hired', $profile?->date_hired?->format('Y-m-d')) }}" class="{{ $inputClass }}">
                        @error('date_hired') <p class="text-xs text-red-600 mt-1">{{ $message }}</p> @enderror
                    </div>
                    <div>
                        <label class="{{ $labelClass }}">Civil Status</label>
                        <select name="civil_status" class="{{ $inputClass }}">
                            <option value="">Select civil status</option>
                            @foreach(['Single', 'Married', 'Widowed', 'Separated'] as $option)
                                <option value="{{ $option }}" @selected($v('civil_status', $profile) === $option)>{{ $option }}</option>
                            @endforeach
                        </select>
                        @error('civil_status') <p class="text-xs text-red-600 mt-1">{{ $message }}</p> @enderror
                    </div>
                    <div>
                        <label class="{{ $labelClass }}">Gender</label>
                        <select name="gender" class="{{ $inputClass }}">
                            <option value="">Select gender</option>
                            @foreach(['Male', 'Female'] as $option)
                                <option value="{{ $option }}" @selected($v('gender', $profile) === $option)>{{ $option }}</option>
                            @endforeach
                        </select>
                        @error('gender') <p class="text-xs text-red-600 mt-1">{{ $message }}</p> @enderror
                    </div>
                    <div>
                        <label class="{{ $labelClass }}">Mobile Number</label>
                        <input type="tel" name="mobile_number" value="{{ $v('mobile_number', $profile) }}" placeholder="0917 123 4567" class="{{ $inputClass }}">
                        @error('mobile_number') <p class="text-xs text-red-600 mt-1">{{ $message }}</p> @enderror
                    </div>
                    <div>
                        <label class="{{ $labelClass }}">Institutional Email <span class="text-gray-400 font-normal">(optional)</span></label>
                        <input type="email" name="institutional_email" value="{{ $v('institutional_email', $profile) }}" class="{{ $inputClass }}">
                        <p class="{{ $hintClass }}">Optional. Used for online meeting verification.</p>
                        @error('institutional_email') <p class="text-xs text-red-600 mt-1">{{ $message }}</p> @enderror
                    </div>
                    <div>
                        <label class="{{ $labelClass }}">Qualification</label>
                        <input type="text" name="qualification" value="{{ $v('qualification', $profile) }}" class="{{ $inputClass }}">
                        @error('qualification') <p class="text-xs text-red-600 mt-1">{{ $message }}</p> @enderror
                    </div>
                    <div>
                        <label class="{{ $labelClass }}">Specialization</label>
                        <input type="text" name="specialization" value="{{ $v('specialization', $profile) }}" class="{{ $inputClass }}">
                        @error('specialization') <p class="text-xs text-red-600 mt-1">{{ $message }}</p> @enderror
                    </div>
                </div>
            </div>
        @endif

        {{-- Photo (both roles) --}}
        <div class="bg-white border border-gray-200 rounded-xl p-6 sm:p-8">
            <h2 class="text-sm font-semibold text-gray-900 mb-5">Profile Photo</h2>

            <div class="flex flex-wrap items-center gap-5">
                <div class="shrink-0">
                    @if($photo)
                        <img src="{{ $photo }}" alt="{{ $user->display_name }}" class="w-20 h-20 rounded-full object-cover border border-gray-200">
                    @else
                        @include('partials.avatar', ['user' => $user, 'class' => 'w-20 h-20 text-2xl'])
                    @endif
                </div>
                <div class="flex-1 min-w-[220px] space-y-2">
                    <input type="file" name="photo" accept="image/*"
                        class="block w-full text-sm text-gray-500 file:mr-3 file:py-2 file:px-4 file:rounded-lg file:border-0 file:bg-gray-900 file:text-white file:text-xs file:font-medium hover:file:bg-gray-800 file:cursor-pointer">
                    @error('photo') <p class="text-xs text-red-600">{{ $message }}</p> @enderror
                    @if($photo)
                        <label class="flex items-center gap-2 text-sm text-gray-600">
                            <input type="checkbox" name="remove_photo" value="1" class="rounded border-gray-300 text-brand-600 focus:ring-brand-500/20">
                            Remove current photo
                        </label>
                    @endif
                    <p class="{{ $hintClass }}">JPG/PNG, max 2MB.</p>
                </div>
            </div>
        </div>

        @if(! $isStudent)
            {{-- Resume / CV --}}
            <div class="bg-white border border-gray-200 rounded-xl p-6 sm:p-8">
                <h2 class="text-sm font-semibold text-gray-900 mb-1">Resume / CV</h2>
                <p class="text-xs text-gray-400 mb-5">Optional — attach your latest curriculum vitae for your record.</p>

                @if($profile?->resume_path)
                    <p class="text-sm text-gray-600 mb-2">Currently: <span class="font-medium">{{ basename($profile->resume_path) }}</span> — uploading a new file replaces it.</p>
                @endif
                <input type="file" name="resume" accept=".pdf,.doc,.docx"
                    class="block w-full text-sm text-gray-500 file:mr-3 file:py-2 file:px-4 file:rounded-lg file:border-0 file:bg-gray-900 file:text-white file:text-xs file:font-medium hover:file:bg-gray-800 file:cursor-pointer">
                @error('resume') <p class="text-xs text-red-600 mt-1">{{ $message }}</p> @enderror
                <p class="{{ $hintClass }}">PDF or Word, max 5MB.</p>
            </div>
        @endif

        <div class="flex items-center gap-3">
            <button type="submit"
                class="bg-brand-600 hover:bg-brand-700 text-white text-sm font-medium px-5 py-2.5 rounded-lg transition">
                {{ $isFirstTime ? 'Save & Continue to Dashboard' : 'Save Profile' }}
            </button>
            @if(! $isFirstTime)
                <a href="{{ route($isStudent ? 'intern.dashboard' : 'monitor.dashboard') }}"
                   class="text-sm text-gray-500 hover:text-gray-900 transition">Back to Dashboard</a>
            @endif
        </div>
    </form>
</div>
@endsection
