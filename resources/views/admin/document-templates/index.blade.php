@extends('layouts.app')

@section('title', 'Templates')

@section('content')
<div>
    <div class="mb-8">
        <h1 class="text-xl font-semibold tracking-tight text-gray-900">Document Templates</h1>
        <p class="text-sm text-gray-500 mt-0.5">Design each printable form in Word and the system fills it with every intern's data.</p>
    </div>

    {{-- Per-college tabs (System Admin) — coordinators are locked to their
         own college and see it as a plain heading. --}}
    <div class="mb-8">
        @if(auth()->user()->isCoordinator())
            <p class="text-sm font-medium text-gray-700">{{ $selectedCollege->name }}</p>
        @else
            <nav class="flex gap-1 overflow-x-auto border-b border-gray-200" aria-label="Colleges">
                @foreach($colleges as $college)
                    <a href="{{ route('admin.document-templates.college', $college->code) }}"
                        class="px-4 py-2.5 text-sm font-medium whitespace-nowrap border-b-2 transition
                            {{ $college->code === $selectedCollege->code
                                ? 'border-brand-600 text-brand-700'
                                : 'border-transparent text-gray-500 hover:text-gray-800 hover:border-gray-300' }}">
                        {{ $college->name }}
                        @if($college->code === \App\Models\OjtSetting::current()->college_code)
                            <span class="ml-1.5 inline-block rounded-full bg-brand-50 px-1.5 py-0.5 text-[10px] font-medium text-brand-700">active</span>
                        @endif
                    </a>
                @endforeach
            </nav>
            <p class="text-xs text-gray-400 mt-2">Interns generate documents from the college marked <span class="font-medium text-brand-700">active</span> under Settings — the other tabs let you prepare a college's set before it goes live.</p>
        @endif
    </div>

    {{-- How it works — the download → edit → upload loop --}}
    <div class="mb-8 rounded-xl border border-brand-100 bg-brand-50/60 p-5 sm:p-6">
        <h2 class="text-sm font-semibold text-gray-900 mb-3">How templates work</h2>
        <ol class="space-y-2 text-sm text-gray-600">
            <li class="flex gap-3">
                <span class="shrink-0 w-5 h-5 rounded-full bg-brand-600 text-white text-[11px] font-semibold flex items-center justify-center">1</span>
                <span><strong class="font-medium text-gray-900">Download</strong> the starter Word file for the form you want to change.</span>
            </li>
            <li class="flex gap-3">
                <span class="shrink-0 w-5 h-5 rounded-full bg-brand-600 text-white text-[11px] font-semibold flex items-center justify-center">2</span>
                <span><strong class="font-medium text-gray-900">Edit</strong> its layout in Microsoft Word — type a <code class="px-1 py-0.5 rounded bg-white border border-brand-100 text-[12px] text-brand-700">${placeholder}</code> wherever the intern's data should go (see the list below). Each tag is replaced with that intern's real data.</span>
            </li>
            <li class="flex gap-3">
                <span class="shrink-0 w-5 h-5 rounded-full bg-brand-600 text-white text-[11px] font-semibold flex items-center justify-center">3</span>
                <span><strong class="font-medium text-gray-900">Upload</strong> your edited file below. Interns immediately generate that form from your design. Remove it any time to go back to the original.</span>
            </li>
        </ol>
    </div>

    {{-- Reports — compiled from each intern's logged duty days --}}
    <div class="flex items-center gap-3 mb-4">
        <h2 class="text-sm font-semibold text-gray-900">Reports</h2>
        <span class="h-px flex-1 bg-gray-100"></span>
    </div>
    <p class="text-xs text-gray-500 -mt-2 mb-4">Built from each intern's logged duty days — repeating rows and weekly pages are filled in automatically.</p>

    <div class="space-y-6">
        @foreach($reports as $t)
            @include('admin.document-templates.partials.card', ['t' => $t])
        @endforeach
    </div>

    {{-- Requirement forms — the school's required OJT documents --}}
    <div class="flex items-center gap-3 mb-4 mt-12">
        <h2 class="text-sm font-semibold text-gray-900">Requirement Forms</h2>
        <span class="h-px flex-1 bg-gray-100"></span>
        <button type="button" onclick="openPlaceholderModal()"
            class="inline-flex items-center gap-1.5 rounded-lg border border-gray-200 bg-white px-3 py-1.5 text-xs font-medium text-gray-700 hover:bg-gray-50 transition">
            <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M8 3H7a2 2 0 0 0-2 2v5a2 2 0 0 1-2 2 2 2 0 0 1 2 2v5c0 1.1.9 2 2 2h1"/><path d="M16 21h1a2 2 0 0 0 2-2v-5c0-1.1.9-2 2-2a2 2 0 0 1-2-2V5a2 2 0 0 0-2-2h-1"/></svg>
            Placeholders you can use
        </button>
    </div>
    <p class="text-xs text-gray-500 -mt-2 mb-4">The school's required forms. Interns download these pre-filled with their own information; a form with no uploaded design is served as the blank official copy — except the Internship Application Letter and Student Intern's Personal Information, which are always generated per intern from their built-in designs.</p>

    {{-- Placeholder reference modal --}}
    <div id="placeholderModal" class="hidden fixed inset-0 z-50 flex items-center justify-center bg-gray-900/25 px-4"
        onclick="if(event.target === this) closePlaceholderModal()">
        <div class="bg-white border border-gray-200 rounded-xl shadow-xl w-full max-w-3xl max-h-[85vh] flex flex-col">
            <div class="px-5 py-4 border-b border-gray-100 flex items-start justify-between gap-3">
                <div>
                    <h3 class="text-sm font-semibold text-gray-900">Placeholders you can use</h3>
                    <p class="text-xs text-gray-500 mt-0.5">Type any of these into a requirement form; each is replaced with the intern's data. Anything you leave out prints as a blank to fill in by hand.</p>
                </div>
                <button type="button" onclick="closePlaceholderModal()"
                    class="shrink-0 rounded-md p-1 text-gray-400 hover:text-gray-700 hover:bg-gray-100 transition">
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><path d="M18 6 6 18M6 6l12 12"/></svg>
                </button>
            </div>
            <div class="px-5 py-4 overflow-y-auto">

        @php
            $placeholderGroups = [
                'Student' => [
                    'intern_name' => 'Full name (First Last)',
                    'full_name' => 'Full name (Last, First)',
                    'first_name' => 'First name',
                    'last_name' => 'Last name',
                    'student_id' => 'Student ID number',
                    'email' => 'Email address',
                    'department' => 'Program code (e.g. BSIT)',
                    'course_name' => 'Full degree name',
                    'year_level' => 'Year level (number)',
                    'year_level_roman' => 'Year level (I–IV)',
                    'batch' => 'Batch',
                    'contact_number' => 'Mobile / contact number',
                ],
                'Placement' => [
                    'company_name' => 'Cooperating agency / office',
                    'company_address' => 'Office address',
                    'supervisor_name' => 'Training supervisor',
                    'supervisor_title' => "Supervisor's title",
                    'addressee_name' => 'Letter addressee (assigned supervisor)',
                    'addressee_title' => "Addressee's title",
                    'salutation' => 'Salutation (e.g. Sir Juan)',
                    'coordinator_name' => 'OJT coordinator',
                    'coordinator_title' => "Coordinator's title",
                    'coordinator_contact' => "Coordinator's contact number",
                    'target_hours' => 'Required OJT hours',
                ],
                'Dates' => [
                    'date_today' => "Today's date",
                    'letter_date' => 'Letter date (e.g. 04 September 2026)',
                    'ojt_period_start' => 'Training start month (e.g. August 2026)',
                    'training_period' => 'Training period (e.g. August until October 2026)',
                    'school_year' => 'School year (e.g. 2026-2027)',
                ],
                'Personal information' => [
                    'birthdate' => 'Date of birth (e.g. October 5, 2004)',
                    'age' => 'Age (from the birthdate)',
                    'sex' => 'Sex',
                    'height' => 'Height',
                    'weight' => 'Weight',
                    'complexion' => 'Complexion',
                    'disability' => 'Disability (write None if none)',
                    'birth_place' => 'Place of birth',
                    'citizenship' => 'Citizenship',
                    'civil_status' => 'Civil status',
                    'present_address' => 'Present address',
                    'present_contact' => 'Present contact number',
                    'permanent_address' => 'Permanent address',
                    'permanent_contact' => 'Permanent contact number',
                    'father_name' => "Father's name",
                    'father_occupation' => "Father's occupation",
                    'mother_name' => "Mother's name",
                    'mother_occupation' => "Mother's occupation",
                    'parents_address' => "Parents' address",
                    'parents_contact' => "Parents' contact number",
                    'guardian_name' => "Guardian's name",
                    'guardian_contact' => "Guardian's contact number",
                    'middle_name' => 'Middle name',
                ],
            ];
        @endphp

        <div class="grid sm:grid-cols-3 gap-x-6 gap-y-5">
            @foreach($placeholderGroups as $groupLabel => $fields)
                <div>
                    <p class="text-[11px] font-semibold uppercase tracking-widest text-gray-400 mb-2">{{ $groupLabel }}</p>
                    <dl class="space-y-1.5">
                        @foreach($fields as $token => $meaning)
                            <div>
                                <dt><code class="text-[12px] text-brand-700">{{ '${'.$token.'}' }}</code></dt>
                                <dd class="text-[11px] text-gray-500 leading-tight">{{ $meaning }}</dd>
                            </div>
                        @endforeach
                    </dl>
                </div>
            @endforeach
        </div>
        </div>
    </div>
</div>

<script>
    function openPlaceholderModal() {
        document.getElementById('placeholderModal').classList.remove('hidden');
    }
    function closePlaceholderModal() {
        document.getElementById('placeholderModal').classList.add('hidden');
    }
    document.addEventListener('keydown', function (event) {
        if (event.key === 'Escape') closePlaceholderModal();
    });
</script>

    <div class="space-y-6">
        @foreach($requirements as $t)
            @include('admin.document-templates.partials.card', ['t' => $t])
        @endforeach
    </div>
</div>

@include('partials.confirm-modal')
@endsection
