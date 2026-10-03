<?php

namespace App\Services;

use App\Exceptions\TemplateMismatchException;
use App\Models\DocumentTemplate;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use PhpOffice\PhpWord\TemplateProcessor;

/**
 * Stores and resolves the admin-managed Word templates, and mail-merges an
 * intern's data into them with PHPWord. Each form type has at most one active
 * template (uploaded by the admin); when none exists, a report form stays
 * unavailable while a requirement form falls back to its shipped file — the
 * blank official copy, or the built-in macroized starter merged per intern for
 * a type that {@see DocumentTemplate::autofillsByDefault()}. The blank starter
 * files shipped with the app live in public/documents (the per-type path is
 * stored on {@see DocumentTemplate::TYPES}) and are what the admin downloads
 * to edit.
 */
class DocumentTemplateService
{
    /**
     * Documentation photo box, in pixels, for the Weekly Progress Report merge.
     *
     * The height must stay within the template's Documentation photo row
     * (2096 twips ≈ 1.46" ≈ 140px): the row is sized "at least" that tall, so a
     * taller image grows it and pushes the ${photoN_date} row below it off the
     * page, leaving a near-blank overflow page after every week that has photos
     * (a 9-week report ballooned to 15 pages). Keeping the box at/under the row
     * height lets each week stay on exactly one page. Inserted with ratio off so
     * the height is fixed and the fit is deterministic regardless of the source
     * photo's own proportions.
     */
    private const PHOTO_BOX_WIDTH = 120;

    private const PHOTO_BOX_HEIGHT = 135;

    /**
     * The active template row for a form type, or null when none is uploaded.
     */
    public function active(string $type): ?DocumentTemplate
    {
        return DocumentTemplate::query()->where('type', $type)->first();
    }

    /**
     * Absolute filesystem path of the active template file, or null when no
     * template is uploaded (or its file has gone missing) — the signal that the
     * form is unavailable until a template is uploaded.
     */
    public function resolvePath(string $type): ?string
    {
        $template = $this->active($type);

        if (! $template || ! Storage::disk($template->disk)->exists($template->path)) {
            return null;
        }

        return Storage::disk($template->disk)->path($template->path);
    }

    /**
     * Absolute path to the starter file the admin downloads to edit — the
     * macroized design shipped in public/documents, ready to upload as-is.
     */
    public function starterPath(string $type): string
    {
        return public_path('documents/'.DocumentTemplate::TYPES[$type]['starter']);
    }

    /**
     * Absolute path to the file an intern receives when no template has been
     * uploaded and the type has no built-in macroized starter to merge: the
     * type's starter by default, or its dedicated fallback when one is
     * declared (the Internship Application Letter's shipped sample is kept as
     * an offline reference for new-cooperating-agency cases).
     */
    public function fallbackPath(string $type): string
    {
        $file = DocumentTemplate::TYPES[$type]['fallback']
            ?? DocumentTemplate::TYPES[$type]['starter'];

        return public_path('documents/'.$file);
    }

    /**
     * Save an admin-edited .docx as the active template for a type, replacing
     * any previous upload in place.
     */
    public function store(string $type, UploadedFile $file, User $admin): DocumentTemplate
    {
        $disk = 'local';
        $path = "document-templates/{$type}.docx";

        $existing = $this->active($type);
        if ($existing && Storage::disk($existing->disk)->exists($existing->path)) {
            Storage::disk($existing->disk)->delete($existing->path);
        }

        Storage::disk($disk)->putFileAs('document-templates', $file, "{$type}.docx");

        return DocumentTemplate::updateOrCreate(
            ['type' => $type],
            [
                'disk' => $disk,
                'path' => $path,
                'original_name' => $file->getClientOriginalName(),
                'uploaded_by' => $admin->id,
            ],
        );
    }

    /**
     * Remove the active template for a type, leaving that form unavailable to
     * interns until a new template is uploaded.
     */
    public function delete(string $type): void
    {
        $template = $this->active($type);

        if (! $template) {
            return;
        }

        if (Storage::disk($template->disk)->exists($template->path)) {
            Storage::disk($template->disk)->delete($template->path);
        }

        $template->delete();
    }

    /**
     * Mail-merge the Weekly Progress Report. The template's ${week}…${/week}
     * block is cloned once per week (each on its own page); identity fields
     * repeat on every page, and each week's five Monday–Friday day columns and
     * Documentation photos are filled from that week's completed journals.
     *
     * @param  array<string, string>  $globals  intern_name, cooperating_agency, course_name, internship_year, target_hours
     * @param  array<int, array{week_label: string, month_header: string, days: array<int, array{num: string, weekday: string, activity: string, photo: ?string}>}>  $weeks
     */
    public function fillWeekly(string $templatePath, array $globals, array $weeks): string
    {
        $processor = new TemplateProcessor($templatePath);

        // Same wrong-upload guard as fillTimesheet: the weekly report's merge
        // is built around the ${week}…${/week} block it clones per week.
        $this->assertHasPlaceholder($processor, 'week', 'Weekly Progress Report', ['week', 'week_label', 'intern_name']);

        $count = max(count($weeks), 1);
        $processor->cloneBlock('week', $count, true, true);

        foreach (array_values($weeks) as $index => $week) {
            $i = $index + 1;

            foreach ($globals as $key => $value) {
                $processor->setValue("{$key}#{$i}", $this->xmlValue($value));
            }

            $processor->setValue("week_label#{$i}", $this->xmlValue($week['week_label']));
            $processor->setValue("month_header#{$i}", $this->xmlValue($week['month_header']));

            foreach (array_values($week['days']) as $slot => $day) {
                $n = $slot + 1;
                $processor->setValue("day{$n}_num#{$i}", $this->xmlValue($day['num']));
                $processor->setValue("day{$n}_weekday#{$i}", $this->xmlValue($day['weekday']));
                $processor->setValue("day{$n}_activity#{$i}", $this->xmlValue($day['activity']));

                if ($day['photo']) {
                    $processor->setImageValue("photo{$n}#{$i}", [
                        'path' => $day['photo'],
                        'width' => self::PHOTO_BOX_WIDTH,
                        'height' => self::PHOTO_BOX_HEIGHT,
                        'ratio' => false,
                    ]);
                } else {
                    $processor->setValue("photo{$n}#{$i}", '');
                }

                // The day's full date, printed under the photo in the
                // Documentation cells (${photoN_date} in the design). Blank
                // for callers that don't supply it.
                $processor->setValue("photo{$n}_date#{$i}", $this->xmlValue($day['photo_date'] ?? ''));
            }
        }

        // No journals at all: the single cloned block still needs its
        // placeholders cleared so raw ${…} text never reaches the page.
        if ($weeks === []) {
            $this->blankWeek($processor, $globals);
        }

        return $this->toBinary($processor);
    }

    /**
     * Mail-merge the Timesheet. The single ${date}/${time}/${hours} row is
     * cloned once per duty day; the identity, total, and signature fields are
     * simple replacements.
     *
     * @param  array<string, string>  $fields  intern_name, year_degree, company_address, required_hours, total_hours, prepared_name, approved_name, reviewed_name
     * @param  array<int, array{date: string, time: string, hours: string}>  $rows
     */
    public function fillTimesheet(string $templatePath, array $fields, array $rows): string
    {
        $processor = new TemplateProcessor($templatePath);

        // The merge clones the ${date} row once per duty day. A design without
        // it can't be filled — typically the wrong form's file was uploaded into
        // this slot — so fail with an actionable message rather than PHPWord's
        // opaque "Can not clone row" exception.
        $this->assertHasPlaceholder($processor, 'date', 'Timesheet', ['date', 'time', 'hours']);

        foreach ($fields as $key => $value) {
            $processor->setValue($key, $this->xmlValue($value));
        }

        $count = max(count($rows), 1);
        $processor->cloneRow('date', $count);

        foreach (array_values($rows) as $index => $row) {
            $i = $index + 1;
            $processor->setValue("date#{$i}", $this->xmlValue($row['date']));
            $processor->setValue("time#{$i}", $this->xmlValue($row['time']));
            $processor->setValue("hours#{$i}", $this->xmlValue($row['hours']));
        }

        // An intern with no logged days still prints one clean, empty row.
        if ($rows === []) {
            $processor->setValue('date#1', '');
            $processor->setValue('time#1', '');
            $processor->setValue('hours#1', '');
        }

        return $this->toBinary($processor);
    }

    /**
     * Mail-merge a flat set of profile placeholders into a template, then
     * resolve any placeholder the design still carries so a raw ${…} tag never
     * reaches the page. Used by the requirement forms, where every field is a
     * simple one-to-one replacement of the intern's own data — the admin decides
     * which of the available placeholders to drop into each form.
     *
     * A field the intern has no data for (or a token the app doesn't provide)
     * is replaced with its per-field empty marker when one is declared in
     * $perFieldEmpty, or with $globalEmptyValue otherwise: an empty blank by
     * default (to complete by hand), or an explicit marker such as "N/A" when
     * the form passes one.
     *
     * @param  array<string, string>  $profile
     * @param  array<string, string>  $perFieldEmpty  per-field fallback text when the value is blank
     * @param  string  $globalEmptyValue  fallback for fields not listed in $perFieldEmpty
     */
    public function fillProfile(
        string $templatePath,
        array $profile,
        array $perFieldEmpty = [],
        string $globalEmptyValue = '',
        array $images = [],
    ): string {
        $processor = new TemplateProcessor($templatePath);

        // Image markers (e.g. the ${profile_picture} box on the Personal
        // Information sheet) are swapped for real picture files before the
        // text pass, so the trailing unknown-variable cleanup never sees
        // them. Templates without a marker simply skip it, which keeps
        // uploaded designs that omit the picture box working.
        foreach ($images as $marker => $spec) {
            if (in_array($marker, $processor->getVariables(), true)) {
                $processor->setImageValue($marker, $spec + ['ratio' => false]);
            }
        }

        foreach ($profile as $key => $value) {
            $processor->setValue($key, $this->xmlValue($value !== '' ? $value : ($perFieldEmpty[$key] ?? $globalEmptyValue)));
        }

        // Resolve any placeholder we had no value for — an unknown token, or a
        // known field with no data — to $globalEmptyValue, so literal macro text
        // never reaches the form.
        foreach ($processor->getVariables() as $remaining) {
            $processor->setValue($remaining, $this->xmlValue($globalEmptyValue));
        }

        return $this->toBinary($processor);
    }

    /**
     * Fail with an actionable message when the template design lacks a
     * placeholder the merge depends on. $expected lists the tokens a correct
     * design carries, so the notice tells the admin what to look for (and
     * points them at the starter download) instead of leaving a raw
     * PHPWord error page.
     *
     * @param  array<int, string>  $expected
     */
    private function assertHasPlaceholder(TemplateProcessor $processor, string $required, string $label, array $expected): void
    {
        $variables = $processor->getVariables();

        // cloneRow/cloneBlock match on the raw ${…} tag in the XML — exactly
        // what getVariables() reports — so an exact match here is what the
        // clone step needs to find.
        if (in_array($required, $variables, true)) {
            return;
        }

        throw TemplateMismatchException::forReport($label, $expected);
    }

    /**
     * Clear every placeholder of the lone week clone for a blank report.
     *
     * @param  array<string, string>  $globals
     */
    private function blankWeek(TemplateProcessor $processor, array $globals): void
    {
        foreach (array_keys($globals) as $key) {
            $processor->setValue("{$key}#1", $this->xmlValue($globals[$key]));
        }

        $processor->setValue('week_label#1', '');
        $processor->setValue('month_header#1', '');

        for ($n = 1; $n <= 5; $n++) {
            $processor->setValue("day{$n}_num#1", '');
            $processor->setValue("day{$n}_weekday#1", '');
            $processor->setValue("day{$n}_activity#1", '');
            $processor->setValue("photo{$n}#1", '');
            $processor->setValue("photo{$n}_date#1", '');
        }
    }

    /**
     * Escape a text value for safe insertion into the template's XML.
     * PHPWord's setValue() writes values into the document verbatim, so a
     * journal message or office name carrying a bare & (e.g. "SPORTS &
     * ATHLETICS office") would otherwise leave invalid XML and Word refuses
     * to open the generated file.
     */
    private function xmlValue(string $value): string
    {
        return htmlspecialchars($value, ENT_XML1 | ENT_QUOTES, 'UTF-8');
    }

    /**
     * Render the processed template to a .docx byte string via a temp file.
     */
    private function toBinary(TemplateProcessor $processor): string
    {
        $temp = tempnam(sys_get_temp_dir(), 'doctpl_');
        $processor->saveAs($temp);
        $binary = (string) file_get_contents($temp);
        @unlink($temp);

        return $binary;
    }
}
