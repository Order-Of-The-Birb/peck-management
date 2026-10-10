<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreApiUserRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->canWrite() ?? false;
    }

    /**
     * @return array<string, list<mixed>>
     */
    public function rules(): array
    {
        return [
            'gaijin_id' => [
                'required',
                'integer',
                Rule::unique('peck_users', 'gaijin_id'),
            ],
            'discord_id' => [
                'nullable',
                'integer',
            ],
            'tz' => [
                'nullable',
                'integer',
                'between:-11,12',
            ],
            'sqb_part' => [
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
            'gaijin_id.required' => __('A Gaijin ID is required.'),
            'gaijin_id.integer' => __('The Gaijin ID must be an integer.'),
            'gaijin_id.unique' => __('That Gaijin ID is already in use.'),
            'tz.between' => __('The timezone must be between -11 and 12.'),
        ];
    }
}
