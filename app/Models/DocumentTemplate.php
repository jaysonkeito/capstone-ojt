<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * An admin-uploaded Word template for one of the intern's printable forms.
 * There is at most one row per {@see self::TYPES} value; its presence is
 * what makes the system fill that .docx with the intern's data instead of
 * serving the blank shipped starter (requirement forms) or rendering the
 * built-in fallback (reports).
 */
class DocumentTemplate extends Model
{
    /**
     * Reports — compiled from the intern's logged duty days (repeating rows,
     * cloned week blocks, embedded photos). Each has a bespoke fill routine.
     */
    public const CATEGORY_REPORT = 'report';

    /**
     * Requirement forms — the school's OJT requirement documents. Every field
     * is a simple one-to-one merge of the intern's own profile data; when no
     * template is uploaded the intern still gets the blank official form.
     */
    public const CATEGORY_REQUIREMENT = 'requirement';

    public const TYPE_WEEKLY_PROGRESS_REPORT = 'weekly_progress_report';

    public const TYPE_TIMESHEET = 'timesheet';

    public const TYPE_COVER_PAGE = 'cover_page';

    public const TYPE_APPLICATION_LETTER = 'application_letter';

    public const TYPE_PERSONAL_INFORMATION = 'personal_information';

    public const TYPE_TRAINING_AGREEMENT = 'training_agreement';

    public const TYPE_APPROVED_COMPANIES = 'approved_companies';

    public const TYPE_INPLANT_AGREEMENT = 'inplant_agreement';

    public const TYPE_ENDORSEMENT_LETTER = 'endorsement_letter';

    public const TYPE_ACCEPTANCE_FORM = 'acceptance_form';

    public const TYPE_CLEARANCE = 'clearance';

    public const TYPE_CERTIFICATION = 'certification';

    public const TYPE_INTERN_FEEDBACK = 'intern_feedback';

    public const TYPE_SUPERVISOR_FEEDBACK = 'supervisor_feedback';

    public const TYPE_PERFORMANCE_APPRAISAL = 'performance_appraisal';

    /**
     * Every template type the system knows how to fill, with its category, the
     * label shown in the managers, and the blank starter file shipped in
     * public/documents (the path is relative to that folder). Requirement forms
     * are listed in the school's official 1–13 order.
     *
     * A type may declare two extras:
     * - `fallback`: an alternate shipped file served when no template is
     *   uploaded (defaults to the starter).
     * - `autofill`: the shipped starter is macroized, so when no template is
     *   uploaded the intern still receives that design merged with their own
     *   data instead of a fixed official copy.
     *
     * @var array<string, array{category: string, label: string, starter: string, fallback?: string, autofill?: bool}>
     */
    public const TYPES = [
        self::TYPE_WEEKLY_PROGRESS_REPORT => [
            'category' => self::CATEGORY_REPORT,
            'label' => 'Weekly Progress Report',
            'starter' => 'weekly-progress-report-template.docx',
        ],
        self::TYPE_TIMESHEET => [
            'category' => self::CATEGORY_REPORT,
            'label' => 'Timesheet',
            'starter' => 'timesheet-template.docx',
        ],
        self::TYPE_COVER_PAGE => [
            'category' => self::CATEGORY_REQUIREMENT,
            'label' => 'Cover Page',
            // The shipped starter is macroized, so even without an uploaded
            // design the compilation cover comes back with the intern's own
            // name on the Student Trainee line of both the IFS and ISS file
            // pages (rendered in caps, matching the official print) — never a
            // fixed copy carrying another student's name. The untouched
            // official binder (1_cover-page.docx) remains on disk as the
            // source this design was built from.
            'starter' => 'cover-page-template.docx',
            'autofill' => true,
        ],
        self::TYPE_APPLICATION_LETTER => [
            'category' => self::CATEGORY_REQUIREMENT,
            'label' => 'Internship Application Letter',
            'starter' => 'application-letter-template.docx',
            // The shipped starter is fully macroized, so even without an
            // uploaded design the letter is generated per intern (addressed to
            // their assigned supervisor at their office). The sample letter is
            // kept on disk only as an offline reference for new-cooperating-
            // agency cases.
            'fallback' => '2_Internship-Application-Letter_IF-NEW-COOPERATING-AGENCY.docx',
            'autofill' => true,
        ],
        self::TYPE_PERSONAL_INFORMATION => [
            'category' => self::CATEGORY_REQUIREMENT,
            'label' => "Student Intern's Personal Information",
            // The shipped starter is macroized, so even without an uploaded
            // design the sheet comes back filled with the intern's own details
            // (birth, addresses, family) — anything still blank prints N/A.
            'starter' => 'personal-information-template.docx',
            'autofill' => true,
        ],
        self::TYPE_TRAINING_AGREEMENT => [
            'category' => self::CATEGORY_REQUIREMENT,
            'label' => 'Training Agreement & Liability Waiver',
            // The shipped starter is macroized, so even without an uploaded
            // design the agreement comes back filled with the intern's own
            // name and ID — never a blank form with [Student Name] still
            // in the text.
            'starter' => 'training-agreement-template.docx',
            'autofill' => true,
        ],
        self::TYPE_APPROVED_COMPANIES => [
            'category' => self::CATEGORY_REQUIREMENT,
            'label' => 'Board-Approved Companies List',
            'starter' => '5_List-of-Companies_Industries-Approved-by-the-Board-of-Regents.docx',
        ],
        self::TYPE_INPLANT_AGREEMENT => [
            'category' => self::CATEGORY_REQUIREMENT,
            'label' => 'In-Plant Training Agreement',
            // The shipped starter is macroized, so even without an uploaded
            // design the agreement comes back filled with the intern's own
            // name — never a blank form with [Student Name] still
            // in the text.
            'starter' => 'inplant-agreement-template.docx',
            'autofill' => true,
        ],
        self::TYPE_ENDORSEMENT_LETTER => [
            'category' => self::CATEGORY_REQUIREMENT,
            'label' => 'Endorsement Letter',
            // Macroized: the shipped starter is filled per intern (addressee,
            // company, intern, coordinator, college) without an uploaded design.
            'starter' => 'endorsement-letter-template.docx',
            'autofill' => true,
        ],
        self::TYPE_ACCEPTANCE_FORM => [
            'category' => self::CATEGORY_REQUIREMENT,
            'label' => 'Acceptance Form',
            // Macroized: the certificate of acceptance merges the intern,
            // course, college, hours, and training start per intern.
            'starter' => 'acceptance-form-template.docx',
            'autofill' => true,
        ],
        self::TYPE_CLEARANCE => [
            'category' => self::CATEGORY_REQUIREMENT,
            'label' => "Student Trainee's Clearance",
            'starter' => '9_Student-Trainees_Clearance.docx',
        ],
        self::TYPE_CERTIFICATION => [
            'category' => self::CATEGORY_REQUIREMENT,
            'label' => 'Certification',
            'starter' => '10_Certification.docx',
        ],
        self::TYPE_INTERN_FEEDBACK => [
            'category' => self::CATEGORY_REQUIREMENT,
            'label' => "Student Intern's Feedback Form",
            'starter' => '11_Student-Interns-Feedback-Form.docx',
        ],
        self::TYPE_SUPERVISOR_FEEDBACK => [
            'category' => self::CATEGORY_REQUIREMENT,
            'label' => "Training Supervisor's Feedback Form",
            'starter' => '12_Training-Supervisors-Feedback-Form.docx',
        ],
        self::TYPE_PERFORMANCE_APPRAISAL => [
            'category' => self::CATEGORY_REQUIREMENT,
            'label' => "Student Intern's Performance Appraisal",
            'starter' => '13_Student-Interns-Performance-Appraisal-Form.docx',
        ],
    ];

    protected $fillable = [
        'type',
        'disk',
        'path',
        'original_name',
        'uploaded_by',
    ];

    /**
     * The template types in one category, preserving their declared order.
     *
     * @return array<string, array{category: string, label: string, starter: string}>
     */
    public static function typesByCategory(string $category): array
    {
        return array_filter(
            self::TYPES,
            static fn (array $meta): bool => $meta['category'] === $category,
        );
    }

    /**
     * The school's OJT requirement forms, in their official order.
     *
     * @return array<string, array{category: string, label: string, starter: string}>
     */
    public static function requirementTypes(): array
    {
        return self::typesByCategory(self::CATEGORY_REQUIREMENT);
    }

    /**
     * The log-driven reports (Timesheet, Weekly Progress Report).
     *
     * @return array<string, array{category: string, label: string, starter: string}>
     */
    public static function reportTypes(): array
    {
        return self::typesByCategory(self::CATEGORY_REPORT);
    }

    /**
     * Whether the given type is one of the requirement forms.
     */
    public static function isRequirementType(string $type): bool
    {
        return array_key_exists($type, self::requirementTypes());
    }

    /**
     * Whether a requirement form is generated from the intern's own data even
     * when the admin hasn't uploaded a design — its shipped starter is
     * macroized, so the app merges the intern's profile into it at download
     * time instead of serving a fixed official copy.
     */
    public static function autofillsByDefault(string $type): bool
    {
        return (bool) (self::TYPES[$type]['autofill'] ?? false);
    }

    /**
     * The admin who uploaded this template.
     */
    public function uploader(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }
}
