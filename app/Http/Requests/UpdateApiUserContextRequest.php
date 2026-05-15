<?php

namespace App\Http\Requests;

use App\Models\PeckUser;
use App\Models\PeckUserContext;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class UpdateApiUserContextRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->level >= 1;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, list<mixed>>
     */
    public function rules(): array
    {
        return [
            'type' => [
                'sometimes',
                'required',
                'string',
                Rule::in(PeckUserContext::TYPES),
            ],
            'from' => [
                'sometimes',
                'required',
                'date_format:Y-m-d',
            ],
            'to' => [
                'sometimes',
                'required',
                'date_format:Y-m-d',
            ],
            'weekdays' => [
                'sometimes',
                'array',
            ],
            'weekdays.*' => [
                'required',
                'integer',
                'between:0,6',
                'distinct',
            ],
            'monthDay' => [
                'sometimes',
                'integer',
                'between:1,31',
            ],
            'comment' => [
                'sometimes',
                'required',
                'string',
                'max:1000',
            ],
        ];
    }

    public function after(): array
    {
        return [
            function (Validator $validator): void {
                $context = $this->existingContext();

                if (! $context instanceof PeckUserContext) {
                    return;
                }

                $typeChanged = $this->has('type');
                $type = $this->string('type', $context->type)->toString();

                $from = $this->has('from') || ! $typeChanged ? $this->input('from', $context->from_date?->format('Y-m-d')) : null;
                $to = $this->has('to') || ! $typeChanged ? $this->input('to', $context->to_date?->format('Y-m-d')) : null;
                $weekdays = $this->has('weekdays') || ! $typeChanged ? $this->input('weekdays', $context->weekdays) : null;
                $monthDay = $this->has('monthDay') || ! $typeChanged ? $this->input('monthDay', $context->month_day) : null;
                $comment = $this->has('comment') || ! $typeChanged ? $this->input('comment', $context->comment) : null;

                if ($this->has('weekdays')) {
                    $monthDay = null;
                }

                if ($this->has('monthDay')) {
                    $weekdays = null;
                }

                if ($type === PeckUserContext::TYPE_ONCE_ABSENCE) {
                    if ($from === null || $from === '') {
                        $validator->errors()->add('from', __('A one-time absence requires a start date.'));
                    }

                    if ($to === null || $to === '') {
                        $validator->errors()->add('to', __('A one-time absence requires an end date.'));
                    }

                    if (is_string($from) && is_string($to) && $from !== '' && $to !== '' && $to < $from) {
                        $validator->errors()->add('to', __('The absence end date must be on or after the start date.'));
                    }
                }

                if ($type === PeckUserContext::TYPE_RECURRING_ABSENCE) {
                    $hasWeekdays = is_array($weekdays) && $weekdays !== [];
                    $hasMonthDay = $monthDay !== null && $monthDay !== '';

                    if (! $hasWeekdays && ! $hasMonthDay) {
                        $validator->errors()->add('weekdays', __('A recurring absence requires weekdays or a month day.'));
                    }
                }

                if ($type === PeckUserContext::TYPE_MISC && ($comment === null || $comment === '')) {
                    $validator->errors()->add('comment', __('A miscellaneous context requires a comment.'));
                }
            },
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'type.in' => __('The selected context type is invalid.'),
            'weekdays.*.between' => __('Weekdays must be zero-indexed values between 0 and 6.'),
            'monthDay.between' => __('The month day must be between 1 and 31.'),
        ];
    }

    private function existingContext(): ?PeckUserContext
    {
        $peckUser = $this->route('peckUser');

        if (! $peckUser instanceof PeckUser) {
            return null;
        }

        return PeckUserContext::query()
            ->where('user_id', $peckUser->gaijin_id)
            ->where('context_id', (int) $this->route('contextId'))
            ->first();
    }
}
