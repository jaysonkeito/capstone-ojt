<?php

namespace App\Http\Requests;

use App\Support\LogTimes;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * A supervisor's or coordinator's review decision on a duty entry:
 * approve, reject (supervisors only), or flag for a second look
 * (coordinators only). A rejection or a flag always carries the reason —
 * it goes to the intern (rejection) or the office's supervisors (flag).
 */
class ReviewLogRequest extends FormRequest
{
    public function authorize(): bool
    {
        $log = $this->route('log');
        $action = $this->input('action');

        return $action === 'flag'
            ? $this->user()->can('flag', $log)
            : $this->user()->can('review', $log);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'action' => ['required', Rule::in(['approve', 'reject', 'flag'])],
            'comment' => [
                Rule::requiredIf(in_array($this->input('action'), ['reject', 'flag'], true)),
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
            'comment.required' => 'A reason is required — it explains the decision to the intern and the admin.',
        ];
    }
}
