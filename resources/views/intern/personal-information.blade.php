@extends('layouts.app')

@section('title', 'Personal Information')

@section('content')
@php
    $labelClass = 'block text-sm font-medium text-gray-700 mb-1.5';
    $inputClass = 'w-full px-3 py-2 rounded-lg border-gray-200 text-sm focus:border-brand-400 focus:ring-2 focus:ring-brand-500/15 transition';
    // Saved value first, falling back to any old() input on a validation bounce.
    $v = fn (string $field) => old($field, $info?->$field);
@endphp

<div class="max-w-3xl mx-auto">
    <div class="mb-8">
        <h1 class="text-xl font-semibold tracking-tight text-gray-900">Personal Information</h1>
        <p class="text-sm text-gray-500 mt-0.5">
            Fill this in once. It's merged into your <span class="font-medium text-gray-700">Student Intern's Personal Information</span>
            form and other requirements, so you don't retype it each time. Anything left blank prints as <span class="font-medium">N/A</span>.
        </p>
    </div>

    <form method="POST" action="{{ route('intern.personal-information.update') }}" class="space-y-6">
        @csrf
        @method('PUT')

        {{-- Identity (read-only — mirrors the Registrar's roster) --}}
        <div class="bg-white border border-gray-200 rounded-xl p-6 sm:p-8">
            <h2 class="text-sm font-semibold text-gray-900 mb-1">Name &amp; Course</h2>
            <p class="text-xs text-gray-400 mb-5">From the Registrar's roster — contact the admin for corrections.</p>

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <div>
                    <label class="{{ $labelClass }}">First Name</label>
                    <input type="text" value="{{ $intern->first_name }}" disabled
                        class="w-full px-3 py-2 rounded-lg border-gray-100 bg-gray-50 text-sm text-gray-400">
                </div>
                <div>
                    <label class="{{ $labelClass }}">Middle Name</label>
                    <input type="text" value="{{ $info?->middle_name }}" disabled
                        class="w-full px-3 py-2 rounded-lg border-gray-100 bg-gray-50 text-sm text-gray-400">
                    <p class="text-[11px] text-gray-400 mt-1">From your Personal Details below.</p>
                </div>
                <div>
                    <label class="{{ $labelClass }}">Last Name</label>
                    <input type="text" value="{{ $intern->last_name }}" disabled
                        class="w-full px-3 py-2 rounded-lg border-gray-100 bg-gray-50 text-sm text-gray-400">
                </div>
                <div>
                    <label class="{{ $labelClass }}">Student ID</label>
                    <input type="text" value="{{ $intern->student_id }}" disabled
                        class="w-full px-3 py-2 rounded-lg border-gray-100 bg-gray-50 text-sm text-gray-400">
                </div>
                <div>
                    <label class="{{ $labelClass }}">Course</label>
                    <input type="text" value="{{ $intern->course_name }}" disabled
                        class="w-full px-3 py-2 rounded-lg border-gray-100 bg-gray-50 text-sm text-gray-400">
                </div>
            </div>
        </div>

        {{-- Personal details --}}
        <div class="bg-white border border-gray-200 rounded-xl p-6 sm:p-8">
            <h2 class="text-sm font-semibold text-gray-900 mb-5">Personal Details</h2>

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <div>
                    <label class="{{ $labelClass }}">Middle Name</label>
                    <input type="text" name="middle_name" value="{{ $v('middle_name') }}" class="{{ $inputClass }}">
                    @error('middle_name') <p class="text-xs text-red-600 mt-1">{{ $message }}</p> @enderror
                </div>
                <div>
                    <label class="{{ $labelClass }}">Date of Birth</label>
                    <input type="date" name="birthdate" value="{{ old('birthdate', $info?->birthdate?->format('Y-m-d')) }}" class="{{ $inputClass }}">
                    @error('birthdate') <p class="text-xs text-red-600 mt-1">{{ $message }}</p> @enderror
                </div>
                <div>
                    <label class="{{ $labelClass }}">Place of Birth</label>
                    <input type="text" name="birth_place" value="{{ $v('birth_place') }}" class="{{ $inputClass }}">
                    @error('birth_place') <p class="text-xs text-red-600 mt-1">{{ $message }}</p> @enderror
                </div>
                <div>
                    <label class="{{ $labelClass }}">Sex</label>
                    <select name="sex" class="{{ $inputClass }}">
                        <option value="">—</option>
                        @foreach (['Male', 'Female'] as $option)
                            <option value="{{ $option }}" @selected($v('sex') === $option)>{{ $option }}</option>
                        @endforeach
                    </select>
                    @error('sex') <p class="text-xs text-red-600 mt-1">{{ $message }}</p> @enderror
                </div>
                <div>
                    <label class="{{ $labelClass }}">Civil Status</label>
                    <select name="civil_status" class="{{ $inputClass }}">
                        <option value="">—</option>
                        @foreach (['Single', 'Married', 'Widowed', 'Separated'] as $option)
                            <option value="{{ $option }}" @selected($v('civil_status') === $option)>{{ $option }}</option>
                        @endforeach
                    </select>
                    @error('civil_status') <p class="text-xs text-red-600 mt-1">{{ $message }}</p> @enderror
                </div>
                <div>
                    <label class="{{ $labelClass }}">Citizenship</label>
                    <input type="text" name="citizenship" value="{{ $v('citizenship') }}" placeholder="Filipino" class="{{ $inputClass }}">
                    @error('citizenship') <p class="text-xs text-red-600 mt-1">{{ $message }}</p> @enderror
                </div>
                <div>
                    <label class="{{ $labelClass }}">Height</label>
                    <input type="text" name="height" value="{{ $v('height') }}" placeholder="e.g. 165 cm" class="{{ $inputClass }}">
                    @error('height') <p class="text-xs text-red-600 mt-1">{{ $message }}</p> @enderror
                </div>
                <div>
                    <label class="{{ $labelClass }}">Weight</label>
                    <input type="text" name="weight" value="{{ $v('weight') }}" placeholder="e.g. 55 kg" class="{{ $inputClass }}">
                    @error('weight') <p class="text-xs text-red-600 mt-1">{{ $message }}</p> @enderror
                </div>
                <div>
                    <label class="{{ $labelClass }}">Complexion</label>
                    <input type="text" name="complexion" value="{{ $v('complexion') }}" class="{{ $inputClass }}">
                    @error('complexion') <p class="text-xs text-red-600 mt-1">{{ $message }}</p> @enderror
                </div>
                <div>
                    <label class="{{ $labelClass }}">Disability <span class="text-gray-400 font-normal text-xs">(write "None" if not applicable)</span></label>
                    <input type="text" name="disability" value="{{ $v('disability') }}" class="{{ $inputClass }}">
                    @error('disability') <p class="text-xs text-red-600 mt-1">{{ $message }}</p> @enderror
                </div>
            </div>
        </div>

        {{-- Contact & addresses --}}
        <div class="bg-white border border-gray-200 rounded-xl p-6 sm:p-8">
            <h2 class="text-sm font-semibold text-gray-900 mb-5">Contact &amp; Addresses</h2>

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <div class="sm:col-span-2">
                    <label class="{{ $labelClass }}">Present Address</label>
                    <input type="text" name="present_address" value="{{ $v('present_address') }}" class="{{ $inputClass }}">
                    @error('present_address') <p class="text-xs text-red-600 mt-1">{{ $message }}</p> @enderror
                </div>
                <div>
                    <label class="{{ $labelClass }}">Present Contact No.</label>
                    <input type="text" name="present_contact" value="{{ $v('present_contact') }}" class="{{ $inputClass }}">
                    @error('present_contact') <p class="text-xs text-red-600 mt-1">{{ $message }}</p> @enderror
                </div>
                <div>
                    <label class="{{ $labelClass }}">Permanent Contact No.</label>
                    <input type="text" name="permanent_contact" value="{{ $v('permanent_contact') }}" class="{{ $inputClass }}">
                    @error('permanent_contact') <p class="text-xs text-red-600 mt-1">{{ $message }}</p> @enderror
                </div>
                <div class="sm:col-span-2">
                    <label class="{{ $labelClass }}">Permanent Address</label>
                    <input type="text" name="permanent_address" value="{{ $v('permanent_address') }}" class="{{ $inputClass }}">
                    @error('permanent_address') <p class="text-xs text-red-600 mt-1">{{ $message }}</p> @enderror
                </div>
            </div>
        </div>

        {{-- Family background --}}
        <div class="bg-white border border-gray-200 rounded-xl p-6 sm:p-8">
            <h2 class="text-sm font-semibold text-gray-900 mb-5">Family Background</h2>

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <div>
                    <label class="{{ $labelClass }}">Father's Name</label>
                    <input type="text" name="father_name" value="{{ $v('father_name') }}" class="{{ $inputClass }}">
                    @error('father_name') <p class="text-xs text-red-600 mt-1">{{ $message }}</p> @enderror
                </div>
                <div>
                    <label class="{{ $labelClass }}">Father's Occupation</label>
                    <input type="text" name="father_occupation" value="{{ $v('father_occupation') }}" class="{{ $inputClass }}">
                    @error('father_occupation') <p class="text-xs text-red-600 mt-1">{{ $message }}</p> @enderror
                </div>
                <div>
                    <label class="{{ $labelClass }}">Mother's Name</label>
                    <input type="text" name="mother_name" value="{{ $v('mother_name') }}" class="{{ $inputClass }}">
                    @error('mother_name') <p class="text-xs text-red-600 mt-1">{{ $message }}</p> @enderror
                </div>
                <div>
                    <label class="{{ $labelClass }}">Mother's Occupation</label>
                    <input type="text" name="mother_occupation" value="{{ $v('mother_occupation') }}" class="{{ $inputClass }}">
                    @error('mother_occupation') <p class="text-xs text-red-600 mt-1">{{ $message }}</p> @enderror
                </div>
                <div class="sm:col-span-2">
                    <label class="{{ $labelClass }}">Parents' Address</label>
                    <input type="text" name="parents_address" value="{{ $v('parents_address') }}" class="{{ $inputClass }}">
                    @error('parents_address') <p class="text-xs text-red-600 mt-1">{{ $message }}</p> @enderror
                </div>
                <div>
                    <label class="{{ $labelClass }}">Parents' Contact No.</label>
                    <input type="text" name="parents_contact" value="{{ $v('parents_contact') }}" class="{{ $inputClass }}">
                    @error('parents_contact') <p class="text-xs text-red-600 mt-1">{{ $message }}</p> @enderror
                </div>
                <div class="hidden sm:block"></div>
                <div>
                    <label class="{{ $labelClass }}">Guardian's Name</label>
                    <input type="text" name="guardian_name" value="{{ $v('guardian_name') }}" class="{{ $inputClass }}">
                    @error('guardian_name') <p class="text-xs text-red-600 mt-1">{{ $message }}</p> @enderror
                </div>
                <div>
                    <label class="{{ $labelClass }}">Guardian's Contact No.</label>
                    <input type="text" name="guardian_contact" value="{{ $v('guardian_contact') }}" class="{{ $inputClass }}">
                    @error('guardian_contact') <p class="text-xs text-red-600 mt-1">{{ $message }}</p> @enderror
                </div>
            </div>
        </div>

        {{-- Mirrors the school sheet's "In case of an emergency" block; left
             blank it falls back to the guardian / parents details above. --}}
        <div class="bg-white border border-gray-200 rounded-xl p-5">
            <h2 class="text-sm font-semibold text-gray-900 mb-4">In case of an emergency, please notify</h2>
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <div>
                    <label class="{{ $labelClass }}">Name</label>
                    <input type="text" name="emergency_name" value="{{ $v('emergency_name') }}" placeholder="{{ $info?->guardian_name }}" class="{{ $inputClass }}">
                    @error('emergency_name') <p class="text-xs text-red-600 mt-1">{{ $message }}</p> @enderror
                </div>
                <div>
                    <label class="{{ $labelClass }}">Relationship</label>
                    <input type="text" name="emergency_relationship" value="{{ $v('emergency_relationship') }}" placeholder="e.g. Mother" class="{{ $inputClass }}">
                    @error('emergency_relationship') <p class="text-xs text-red-600 mt-1">{{ $message }}</p> @enderror
                </div>
                <div>
                    <label class="{{ $labelClass }}">Address</label>
                    <input type="text" name="emergency_address" value="{{ $v('emergency_address') }}" placeholder="{{ $info?->parents_address }}" class="{{ $inputClass }}">
                    @error('emergency_address') <p class="text-xs text-red-600 mt-1">{{ $message }}</p> @enderror
                </div>
                <div>
                    <label class="{{ $labelClass }}">Contact No.</label>
                    <input type="text" name="emergency_contact" value="{{ $v('emergency_contact') }}" placeholder="{{ $info?->parents_contact }}" class="{{ $inputClass }}">
                    @error('emergency_contact') <p class="text-xs text-red-600 mt-1">{{ $message }}</p> @enderror
                </div>
            </div>
        </div>

        <div class="flex items-center gap-3">
            <button type="submit" class="bg-brand-600 hover:bg-brand-700 text-white text-sm font-medium px-4 py-2 rounded-lg transition">Save Personal Information</button>
            <a href="{{ route('intern.requirements.index') }}" class="text-sm text-gray-500 hover:text-gray-900 transition">Back to Requirements</a>
        </div>
    </form>
</div>
@endsection
