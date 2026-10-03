<?php

namespace App\Http\Requests;

use App\Support\LogTimes;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * A supervisor's manual attendance entry for an intern at their office —
 * the fix for a missed scan. The entry starts as pending for the admin to
 * confirm, so the intern and date are validated here while the office
 * scoping is enforced in the controller via InternPolicy::createLog.
 */
class ManualLogRequest extends FormRequest
{
    public function authorize(): bool
    {
        // The route is restricted to the supervisor role; per-intern office
        // scoping happens in the controller once the intern is resolved.
        return $this->user()->isSupervisor();
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'user_id' => ['required', 'exists:users,id'],
            'date' => [
                'required',
                'date',
                'before_or_equal:today',
                Rule::unique('ojt_logs', 'date')->where('user_id', $this->input('user_id')),
            ],
            ...LogTimes::rules(),
            'notes' => ['nullable', 'string', 'max:2000'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'date.unique' => 'This intern already has a logged entry for that date.',
        ];
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            LogTimes::validate($validator, $this->all());
        });
    }
}
