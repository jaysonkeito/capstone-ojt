<?php

namespace App\Support;

use Illuminate\Validation\Validator;

/**
 * Shared validation for manual log entry forms (admin Logbook and the
 * supervisor's "missing entry" form). A partial entry is allowed — a lone
 * time records the scan the intern missed and later times fill in — but a
 * wholly blank entry records nothing, and within a session the times must
 * ascend in day order: In → Out → In (2) → Out (2).
 */
class LogTimes
{
    /**
     * The eight clock slots in day order, alternating in/out.
     *
     * @var list<string>
     */
    public const FIELDS = [
        'am_time_in',
        'am_time_out',
        'am_time_in_2',
        'am_time_out_2',
        'pm_time_in',
        'pm_time_out',
        'pm_time_in_2',
        'pm_time_out_2',
    ];

    /**
     * Validation rules for the eight time slots (all optional at the rule
     * level — the ordering checks below decide what combinations are legal).
     *
     * @return array<string, list<string>>
     */
    public static function rules(): array
    {
        return array_combine(
            self::FIELDS,
            array_fill(0, count(self::FIELDS), ['nullable', 'date_format:H:i']),
        );
    }

    /**
     * Apply the blank-entry and ordering checks to a validator.
     */
    public static function validate(Validator $validator, array $times): void
    {
        $times = collect(self::FIELDS)
            ->mapWithKeys(fn (string $field) => [$field => $times[$field] ?? null]);

        if (! $times->contains(fn ($time) => filled($time))) {
            $validator->errors()->add('am_time_in', 'Enter at least one time — AM or PM, in or out.');
        }

        $isTime = fn ($value) => is_string($value) && preg_match('/^\d{2}:\d{2}$/', $value) === 1;

        // Comparing the zero-padded 'H:i' strings is equivalent to comparing
        // the clock times. `ascending` actually means "descends" — the later
        // slot must NOT be at or before the earlier one.
        $ascending = fn (string $later, string $earlier) =>
            $isTime($times[$later]) && $isTime($times[$earlier]) && $times[$later] <= $times[$earlier];

        $pairs = [
            ['am_time_out', 'am_time_in', 'AM Time Out must be after AM Time In.'],
            ['am_time_in_2', 'am_time_out', 'AM Time In (2) must be after AM Time Out — it records coming back from a step out.'],
            ['am_time_out_2', 'am_time_in_2', 'AM Time Out (2) must be after AM Time In (2).'],
            ['pm_time_out', 'pm_time_in', 'PM Time Out must be after PM Time In.'],
            ['pm_time_in_2', 'pm_time_out', 'PM Time In (2) must be after PM Time Out — it records coming back from a step out.'],
            ['pm_time_out_2', 'pm_time_in_2', 'PM Time Out (2) must be after PM Time In (2).'],
        ];

        foreach ($pairs as [$later, $earlier, $message]) {
            if ($ascending($later, $earlier)) {
                $validator->errors()->add($later, $message);
            }
        }
    }
}
