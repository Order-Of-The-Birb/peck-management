<?php

namespace App\Http\Requests;

use App\Models\PeckLeaveInfo;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpsertApiUserLeaveInfoRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->level >= 1;
    }

    /**
     * @return array<string, list<mixed>>
     */
    public function rules(): array
    {
        return [
            'type' => [
                'required',
                'string',
                Rule::in(PeckLeaveInfo::TYPES),
            ],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'type.required' => __('A leave info type is required.'),
            'type.in' => __('The selected leave info type is invalid.'),
        ];
    }
}
