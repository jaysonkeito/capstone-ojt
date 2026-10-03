<?php

namespace App\Services;

use App\Models\DocumentTemplate;
use App\Models\InternPersonalInfo;
use App\Models\OjtSetting;
use App\Models\User;
use App\Support\RenderedDocument;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

/**
 * Produces one of the school's OJT requirement forms for an intern. When an
 * admin has uploaded a Word design for that form, the intern's own profile is
 * mail-merged into it (name, course, placement, coordinator, dates, …) so the
 * download comes back pre-filled. Forms whose shipped starter is macroized
 * (the Internship Application Letter) are merged the same way even without an
 * uploaded design, so every intern always receives their own letter. The rest
 * fall back to the blank official form shipped in public/documents.
 */
class RequirementDocumentService
{
    /**
     * Requirement forms that mark a missing field with an explicit token
     * instead of leaving it blank. The Personal Information sheet is a
     * filled-in data form, so an absent value should read "N/A" rather than an
     * empty line; keyed by form type, defaulting to a blank when unlisted.
     *
     * @var array<string, string>
     */
    private const EMPTY_DEFAULTS = [
        DocumentTemplate::TYPE_PERSONAL_INFORMATION => 'N/A',
        DocumentTemplate::TYPE_COVER_PAGE => [
            'contact_number' => 'Your Cellphone Number',
        ],
        DocumentTemplate::TYPE_TRAINING_AGREEMENT => [
            'parent_name' => 'Parent/Guardian Name',
        ],
    ];

    public function __construct(private DocumentTemplateService $templates) {}

    /**
     * Whether the admin has uploaded a fillable design for this form (versus
     * the intern receiving the blank official copy).
     */
    public function hasCustomTemplate(string $type): bool
    {
        return $this->templates->resolvePath($type) !== null;
    }

    /**
     * Whether downloading this form returns the intern's own data merged in —
     * through an admin-uploaded design or the type's built-in macroized starter
     * — versus the blank official copy.
     */
    public function isFilledForIntern(string $type): bool
    {
        return $this->hasCustomTemplate($type) || DocumentTemplate::autofillsByDefault($type);
    }

    /**
     * The requirement form as a ready-to-stream Word document: the admin's
     * uploaded template filled with the intern's data, the built-in macroized
     * starter filled per intern when the form ships one, or the blank official
     * copy when neither applies.
     */
    public function render(User $intern, string $type): RenderedDocument
    {
        $filename = $this->filename($intern, DocumentTemplate::TYPES[$type]['label']);
        $profile = $this->profile($intern);
        $templatePath = $this->templates->resolvePath($type);

        // Build per-field empty values for forms that declare them (e.g. the
        // cover page shows "Your Cellphone Number" when contact_number is
        // blank). A form whose entry is a plain string instead declares a
        // global marker ("N/A" on the Personal Information sheet) that fills
        // every blank field the intern has no data for.
        $declared = self::EMPTY_DEFAULTS[$type] ?? [];
        $emptyValues = is_array($declared) ? $declared : [];
        $globalEmptyValue = is_string($declared) ? $declared : '';

        // The admin's uploaded design (when one exists) is the source of truth;
        // the intern's profile is merged into it.
        if ($templatePath) {
            $binary = $this->templates->fillProfile(
                $templatePath,
                $profile,
                $emptyValues,
                $globalEmptyValue,
            );

            return RenderedDocument::word($binary, $filename);
        }

        // No uploaded design. A form whose shipped starter is macroized is
        // still generated per intern — the Internship Application Letter comes
        // out addressed to the intern's own supervisor and office, never a
        // fixed copy of another student's letter.
        if (DocumentTemplate::autofillsByDefault($type)) {
            $binary = $this->templates->fillProfile(
                $this->templates->starterPath($type),
                $profile,
                $emptyValues,
                $globalEmptyValue,
            );

            return RenderedDocument::word($binary, $filename);
        }

        // Everything else is handed back as the blank official copy exactly as
        // shipped (the type's fallback file when one is declared).
        $blank = $this->templates->fallbackPath($type);

        return RenderedDocument::word((string) file_get_contents($blank), $filename);
    }

    /**
     * The intern's profile as the flat placeholder set the requirement forms
     * merge from. Every value is a plain string (empty when not on file), so a
     * template can reference any of these; unfilled ones print as blanks, or as
     * the form's empty marker (e.g. "N/A") where one is configured.
     *
     * Academic and placement tokens come straight off the roster; the personal
     * tokens (birth details, addresses, contacts, family) come from the intern's
     * own {@see InternPersonalInfo}, which they fill in once.
     *
     * @return array<string, string>
     */
    public function profile(User $intern): array
    {
        $supervisor = $intern->office?->supervisors()->first();
        $coordinator = $intern->coordinator;
        $enrollment = $intern->currentEnrollment;
        $targetHours = $enrollment?->target_hours ?? $intern->target_hours;
        $info = $intern->personalInfo;
        $birthdate = $info?->birthdate;
        $supervisorGender = strtolower((string) $supervisor?->staffProfile?->gender);

        return [
            // Academic & placement — derivable from the core roster.
            'intern_name' => (string) $intern->display_name,
            'full_name' => (string) $intern->full_name,
            'first_name' => (string) $intern->first_name,
            'middle_name' => (string) ($info?->middle_name ?? ''),
            'last_name' => (string) $intern->last_name,
            'student_id' => (string) ($intern->student_id ?? ''),
            'email' => (string) ($intern->email ?? ''),
            'department' => (string) ($intern->department ?? ''),
            'course_name' => (string) $intern->course_name,
            'year_level' => (string) ($intern->year_level ?? ''),
            'year_level_roman' => (string) ($intern->year_level_roman ?? ''),
            'year_level_word' => $this->yearLevelWord($intern->year_level),
            'batch' => (string) ($intern->batch ?? ''),
            'company_name' => (string) ($intern->office?->name ?? ''),
            'company_address' => (string) ($intern->office?->address ?? ''),
            'supervisor_name' => (string) ($supervisor?->display_name_with_middle_initial ?? ''),
            'supervisor_title' => (string) ($supervisor?->title ?? ''),
            'supervisor_position' => (string) ($supervisor?->position ?? ''),
            // Application-letter recipient — the supervisor at the intern's
            // office, addressed formally (Mr./Ms. + Sir/Ma'am when their staff
            // record says which).
            'addressee_name' => $this->honorific($supervisorGender).(string) ($supervisor?->display_name_with_middle_initial ?? ''),
            'addressee_title' => (string) ($supervisor?->title ?? ''),
            'addressee_position' => (string) ($supervisor?->position ?? ''),
            'salutation' => $this->salutation($supervisorGender, (string) ($supervisor?->first_name ?? '')),
            'coordinator_name' => (string) ($coordinator?->display_name_with_middle_initial ?? ''),
            'coordinator_title' => (string) ($coordinator?->title ?? ''),
            'coordinator_position' => (string) ($coordinator?->position ?? ''),
            'coordinator_contact' => (string) ($coordinator?->staffProfile?->mobile_number ?? ''),
            'target_hours' => (string) $targetHours,
            'college_name' => (string) (OjtSetting::current()->college_name ?: 'College of Arts and Sciences'),
            'date_today' => now()->format('F j, Y'),
            'letter_date' => now()->format('d F Y'),
            // The month the intern's own OJT set begins, used by the shipped
            // application letter's "from … until I complete my hours" line. Falls
            // back to the campus-wide period start from Settings.
            'ojt_period_start' => $this->ojtPeriodStart($enrollment?->started_at),
            'training_period' => $this->trainingPeriod($enrollment?->started_at, $enrollment?->completed_at),
            'school_year' => $this->schoolYear(),

            // Personal information — the intern's own entry; empty until filled.
            'birthdate' => $birthdate ? $birthdate->format('F j, Y') : '',
            'age' => $birthdate ? (string) $birthdate->age : '',
            'sex' => (string) ($info?->sex ?? ''),
            'height' => (string) ($info?->height ?? ''),
            'weight' => (string) ($info?->weight ?? ''),
            'complexion' => (string) ($info?->complexion ?? ''),
            'disability' => (string) ($info?->disability ?? ''),
            'birth_place' => (string) ($info?->birth_place ?? ''),
            'citizenship' => (string) ($info?->citizenship ?? ''),
            'civil_status' => (string) ($info?->civil_status ?? ''),
            'present_address' => (string) ($info?->present_address ?? ''),
            'present_contact' => (string) ($info?->present_contact ?? ''),
            'permanent_address' => (string) ($info?->permanent_address ?? ''),
            'permanent_contact' => (string) ($info?->permanent_contact ?? ''),
            // The mobile number interns put in their profile completion sheet,
            // falling back to the older contact fields when it's not on file.
            'contact_number' => (string) ($info?->phone_number ?: $info?->present_contact ?: $info?->permanent_contact ?? ''),
            'father_name' => (string) ($info?->father_name ?? ''),
            'father_occupation' => (string) ($info?->father_occupation ?? ''),
            'mother_name' => (string) ($info?->mother_name ?? ''),
            'mother_occupation' => (string) ($info?->mother_occupation ?? ''),
            'parents_address' => (string) ($info?->parents_address ?? ''),
            'parents_contact' => (string) ($info?->parents_contact ?? ''),
            'guardian_name' => (string) ($info?->guardian_name ?? ''),
            'guardian_contact' => (string) ($info?->guardian_contact ?? ''),
            // The sheet's "In case of an emergency" block — the intern's own
            // entry, falling back to the guardian and parents details when
            // they left it blank so the block never prints empty.
            'emergency_name' => (string) ($info?->emergency_name ?: $info?->guardian_name ?? ''),
            'emergency_relationship' => (string) ($info?->emergency_relationship ?? ''),
            'emergency_address' => (string) ($info?->emergency_address ?: $info?->parents_address ?? ''),
            'emergency_contact' => (string) ($info?->emergency_contact ?: $info?->parents_contact ?: $info?->guardian_contact ?? ''),
            // Alias for training agreement template - parent/guardian name
            // ONLY use guardian_name - do NOT fall back to contact numbers
            'parent_name' => (string) ($info?->guardian_name ?? ''),
        ];
    }

    /**
     * The year level written out ("First"…"Fourth") as some forms phrase it,
     * falling back to the numeric value for anything outside the usual 1–4.
     */
    private function yearLevelWord(int|string|null $level): string
    {
        return match ((int) $level) {
            1 => 'First',
            2 => 'Second',
            3 => 'Third',
            4 => 'Fourth',
            default => $level ? (string) $level : '',
        };
    }

    /**
     * "Mr. " or "Ms. " for the letter's addressee, matching the supervisor's
     * gender on file; an empty prefix when it isn't recorded.
     */
    private function honorific(string $gender): string
    {
        return match ($gender) {
            'male' => 'Mr. ',
            'female' => 'Ms. ',
            default => '',
        };
    }

    /**
     * The letter's opening "Sir {First}" / "Ma'am {First}" (or just the first
     * name when the supervisor's gender isn't on file).
     */
    private function salutation(string $gender, string $firstName): string
    {
        return match ($gender) {
            'male' => 'Sir '.$firstName,
            'female' => "Ma'am ".$firstName,
            default => $firstName,
        };
    }

    /**
     * "August 2026"-style start month for the application letter: the
     * intern's own enrollment start when one is on file, otherwise the OJT
     * period start configured in Settings. Blank only when neither exists.
     */
    private function ojtPeriodStart(?Carbon $startedAt): string
    {
        $start = $startedAt ?? OjtSetting::current()->training_starts_on;

        return $start ? $start->format('F Y') : '';
    }

    /**
     * The training window as a letter phrase, e.g. "August until October 2026"
     * for an internship inside one calendar year, or "December 2025 until
     * February 2026" when it spans the new year. Blank when the enrollment has
     * no start date yet.
     */
    private function trainingPeriod(?Carbon $startedAt, ?Carbon $completedAt): string
    {
        if (! $startedAt) {
            return '';
        }

        if ($completedAt && $startedAt->year === $completedAt->year) {
            return $startedAt->format('F').' until '.$completedAt->format('F Y');
        }

        if ($completedAt) {
            return $startedAt->format('F Y').' until '.$completedAt->format('F Y');
        }

        return $startedAt->format('F Y');
    }

    /**
     * The current academic year as "YYYY-YYYY". A Philippine school year runs
     * roughly June–May, so June is treated as the rollover into the new year.
     */
    private function schoolYear(): string
    {
        $now = Carbon::now();
        $startYear = $now->month >= 6 ? $now->year : $now->year - 1;

        return $startYear.'-'.($startYear + 1);
    }

    /**
     * "Cover_Page_Lastname_Firstname.docx" — the form label and intern name,
     * stripped to filesystem-safe tokens.
     */
    private function filename(User $intern, string $label): string
    {
        $labelPart = Str::of($label)
            ->replaceMatches('/[^A-Za-z0-9]+/', '_')
            ->trim('_');

        $namePart = Str::of("{$intern->last_name} {$intern->first_name}")
            ->replaceMatches('/[^A-Za-z0-9]+/', '_')
            ->trim('_');

        return "{$labelPart}_{$namePart}.docx";
    }
}
