<?php

namespace App\Exceptions;

use Exception;

/**
 * An admin-uploaded Word template doesn't match the design the app knows how
 * to fill — most often the wrong form's file uploaded into a slot (e.g. the
 * Weekly Progress Report template stored as the Timesheet), so the repeating
 * placeholder the merge clones (the Timesheet's ${date} row, the weekly
 * report's ${week}…${/week} block) is missing. Report downloads catch it and
 * redirect with a notice instead of dying with a 500.
 */
class TemplateMismatchException extends Exception
{
    /**
     * Build the actionable notice for a report template that lacks the
     * placeholder its merge is built around, naming what a correct design
     * carries and how to fix the upload.
     *
     * @param  array<int, string>  $expected
     */
    public static function forReport(string $label, array $expected): static
    {
        $placeholders = implode(', ', array_map(fn (string $name): string => '${'.$name.'}', $expected));

        return new static(
            "The {$label} template can't be filled — it doesn't match the design this form is built around ".
            "(a correct template carries {$placeholders}). Please download the {$label} starter from the ".
            'Document Templates page, apply your edits to it, and upload it again.'
        );
    }
}
