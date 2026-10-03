<?php

use App\Services\TimesheetReportService;

/*
 * The Timesheet's "Number of Hours" values are whole minutes rendered as
 * words: a day worked to 5:05 PM reads "8 hours 5 minutes", while a whole-hour
 * day stays clean ("4 hours", never "4 hours 0 minutes"), and the total sums
 * the same way. TimesheetReportService::formatMinutes() is the single source
 * of that wording for both the per-day rows and the total field, so we assert
 * it directly rather than through the generated Word file.
 */

test('formatMinutes renders partial hours as words and keeps whole hours clean', function (int $minutes, string $expected) {
    expect(app(TimesheetReportService::class)->formatMinutes($minutes))->toBe($expected);
})->with([
    'partial-hour day'        => [485, '8 hours 5 minutes'],  // 08:00–12:00 + 13:00–17:05
    'whole-hour day'          => [240, '4 hours'],            // never "4 hours 0 minutes"
    'total of the two days'   => [725, '12 hours 5 minutes'], // 485 + 240
    'singular hour and minute' => [61, '1 hour 1 minute'],    // both singular
    'minutes only'            => [5, '5 minutes'],
    'zero'                    => [0, '0 hours'],
]);
