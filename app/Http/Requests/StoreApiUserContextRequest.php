<?php

namespace App\Http\Requests;

use App\Models\PeckUserContext;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class StoreApiUserContextRequest extends FormRequest
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
                'required',
                'string',
                Rule::in(PeckUserContext::TYPES),
            ],
            'from' => [
                'required_if:type,'.PeckUserContext::TYPE_ONCE_ABSENCE,
                'date_format:Y-m-d',
            ],
            'to' => [
                'required_if:type,'.PeckUserContext::TYPE_ONCE_ABSENCE,
                'date_format:Y-m-d',
                'after_or_equal:from',
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
                'required_if:type,'.PeckUserContext::TYPE_MISC,
                'string',
                'max:1000',
            ],
        ];
    }

    public function after(): array
    {
        return [
            function (Validator $validator): void {
                if ($this->input('type') !== PeckUserContext::TYPE_RECURRING_ABSENCE) {
                    return;
                }

                $hasWeekdays = $this->has('weekdays') && is_array($this->input('weekdays')) && $this->input('weekdays') !== [];
                $hasMonthDay = $this->has('monthDay') && $this->input('monthDay') !== null && $this->input('monthDay') !== '';

                if (! $hasWeekdays && ! $hasMonthDay) {
                    $validator->errors()->add('weekdays', __('A recurring absence requires weekdays or a month day.'));
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
            'type.required' => __('A context type is required.'),
            'type.in' => __('The selected context type is invalid.'),
            'from.required_if' => __('A one-time absence requires a start date.'),
            'to.required_if' => __('A one-time absence requires an end date.'),
            'to.after_or_equal' => __('The absence end date must be on or after the start date.'),
            'weekdays.*.between' => __('Weekdays must be zero-indexed values between 0 and 6.'),
            'monthDay.between' => __('The month day must be between 1 and 31.'),
            'comment.required_if' => __('A miscellaneous context requires a comment.'),
        ];
    }
}
