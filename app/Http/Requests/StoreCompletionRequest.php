<?php

namespace App\Http\Requests;

use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;

/**
 * An OJT coordinator recommending that one of their interns (who has
 * reached the target hours) be marked as having completed the OJT set.
 * The hours check and the one-pending-request rule live here so the
 * admin's queue only ever carries recommendations that make sense.
 */
class StoreCompletionRequest extends FormRequest
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
            'intern_id' => ['required', 'exists:users,id'],
            'note' => ['nullable', 'string', 'max:2000'],
        ];
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            $intern = User::find($this->input('intern_id'));

            if (! $intern) {
                return;
            }

            $enrollment = $intern->currentEnrollment;

            if (! $enrollment || ! $intern->is_complete) {
                $validator->errors()->add('intern_id', "{$intern->full_name} hasn't reached the target hours yet — completion can only be recommended once they have.");
            } elseif ($enrollment->status === 'completed') {
                $validator->errors()->add('intern_id', "{$intern->full_name}'s OJT set is already completed.");
            } elseif (\App\Models\CompletionRecommendation::where('intern_id', $intern->id)->pending()->exists()) {
                $validator->errors()->add('intern_id', "{$intern->full_name} already has a pending completion recommendation awaiting the System Admin.");
            }
        });
    }
}
