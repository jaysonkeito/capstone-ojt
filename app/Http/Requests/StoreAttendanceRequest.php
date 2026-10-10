<?php

namespace App\Http\Requests;

use App\Models\LogRequest;
use App\Models\OjtLog;
use App\Support\LogTimes;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * An intern's attendance request: a correction (the recorded scans on a
 * duty day are wrong or incomplete — the proposed times travel with the
 * request) or an absence report (a duty day with no entry, for the
 * record). The supervisor or coordinator decides; an approved correction
 * is applied to the log by RequestDecider.
 */
class StoreAttendanceRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->isIntern();
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'type' => ['required', Rule::in([LogRequest::TYPE_CORRECTION, LogRequest::TYPE_ABSENCE])],
            'date' => ['required', 'date', 'before_or_equal:today'],
            ...LogTimes::rules(),
            'reason' => ['required', 'string', 'max:2000'],
            // Up to two photos as proof — an excuse note, the corrected
            // entry, whatever shows the truth of the day.
            'proofs' => ['nullable', 'array', 'max:2'],
            'proofs.*' => ['image', 'mimes:jpeg,jpg,png,webp', 'max:4096'],
        ];
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            $intern = $this->user();
            $date = $this->input('date');
            $type = $this->input('type');

            if (! is_string($date) || $date === '' || ! in_array($type, [LogRequest::TYPE_CORRECTION, LogRequest::TYPE_ABSENCE], true)) {
                return;
            }

            $hasLog = OjtLog::where('user_id', $intern->id)->whereDate('date', $date)->exists();

            if ($type === LogRequest::TYPE_CORRECTION && ! $hasLog) {
                $validator->errors()->add('date', "You don't have a duty entry for that date — report it as an absence instead.");
            }

            if ($type === LogRequest::TYPE_ABSENCE && $hasLog) {
                $validator->errors()->add('date', 'You already have a duty entry for that date — request a correction instead.');
            }

            if (LogRequest::where('intern_id', $intern->id)->whereDate('date', $date)->where('status', LogRequest::STATUS_PENDING)->exists()) {
                $validator->errors()->add('date', 'You already have a pending request for that date.');
            }

            if ($type === LogRequest::TYPE_CORRECTION) {
                LogTimes::validate($validator, $this->all());
            }
        });
    }
}
