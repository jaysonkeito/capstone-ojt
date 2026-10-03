<?php

namespace App\Http\Requests;

use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * An OJT coordinator proposing an office for one of their interns. The
 * intern scoping lives in InternPolicy::monitor, and the
 * one-pending-request rule is checked here so the admin's queue never
 * carries two open proposals for the same intern.
 */
class StorePlacementRequest extends FormRequest
{
    public function authorize(): bool
    {
        $intern = User::find($this->input('intern_id'));

        return $this->user()->isCoordinator()
            && $intern !== null
            && $this->user()->can('monitor', $intern);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'intern_id' => ['required', Rule::exists('users', 'id')->where('role', 'intern')],
            'office_id' => ['required', Rule::exists('offices', 'id')],
            'note' => ['nullable', 'string', 'max:2000'],
        ];
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            $intern = User::find($this->input('intern_id'));

            if ($intern && \App\Models\PlacementRequest::where('intern_id', $intern->id)->pending()->exists()) {
                $validator->errors()->add('intern_id', "{$intern->full_name} already has a pending placement request awaiting the System Admin.");
            }
        });
    }
}
