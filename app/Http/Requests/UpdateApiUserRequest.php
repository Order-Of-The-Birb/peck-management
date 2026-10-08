<?php

namespace App\Http\Requests;

use App\Models\PeckUser;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateApiUserRequest extends FormRequest
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
            'discord_id' => [
                'sometimes',
                'nullable',
                'integer',
            ],
            'tz' => [
                'sometimes',
                'nullable',
                'integer',
                'between:-11,12',
            ],
            'status' => [
                'sometimes',
                'required',
                'string',
                Rule::in(PeckUser::STATUSES),
            ],
            'sqb_part' => [
                'sometimes',
                'boolean',
            ],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'status.in' => __('The selected status is invalid.'),
            'tz.between' => __('The timezone must be between -11 and 12.'),
        ];
    }
}
