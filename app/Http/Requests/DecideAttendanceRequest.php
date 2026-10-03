<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * A supervisor's or coordinator's decision on an intern's attendance
 * request. A rejection carries the reason the intern will see; an
 * approval may carry a short note as well.
 */
class DecideAttendanceRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('decide', $this->route('logRequest'));
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'action' => ['required', Rule::in(['approved', 'rejected'])],
            'comment' => [
                Rule::requiredIf($this->input('action') === 'rejected'),
                'nullable',
                'string',
                'max:2000',
            ],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'comment.required' => 'A reason is required — the intern will see why their request was rejected.',
        ];
    }
}
