<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

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
            'tz.between' => __('The timezone must be between -11 and 12.'),
        ];
    }
}
