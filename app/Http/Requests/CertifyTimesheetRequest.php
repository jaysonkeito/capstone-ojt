<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * A supervisor certifying an intern's duty hours for one timesheet
 * period (a calendar month). The office scoping lives in
 * InternPolicy::certify; this request only shapes the period.
 */
class CertifyTimesheetRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('certify', $this->route('intern'));
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'month' => ['nullable', 'date_format:Y-m', 'before_or_equal:'.now()->format('Y-m')],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'month.before_or_equal' => 'You can only certify a period that has already started.',
        ];
    }
}
