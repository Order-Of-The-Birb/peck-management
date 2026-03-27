<?php

namespace App\Http\Requests;

use App\Models\PeckUser;
use Closure;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreApiUserRequest extends FormRequest
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
            'gaijin_id' => [
                'required',
                'integer',
                Rule::unique('peck_users', 'gaijin_id'),
            ],
            'username' => [
                'required',
                'string',
                'max:255',
                Rule::unique('peck_users', 'username'),
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
            'status' => [
                'required',
                'string',
                Rule::in(PeckUser::STATUSES),
            ],
            'joindate' => [
                'nullable',
                'date_format:Y-m-d',
            ],
            'initiator' => [
                'nullable',
                'integer',
                Rule::exists('officers', 'gaijin_id'),
                function (string $attribute, mixed $value, Closure $fail): void {
                    $gaijinId = $this->integer('gaijin_id');

                    if ($value !== null && (int) $value === $gaijinId) {
                        $fail(__('The initiator cannot be the same as the selected peck user.'));
                    }
                },
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
            'username.required' => __('A username is required.'),
            'username.unique' => __('That username is already in use.'),
            'status.required' => __('A status is required.'),
            'status.in' => __('The selected status is invalid.'),
            'tz.between' => __('The timezone must be between -11 and 12.'),
            'initiator.exists' => __('The selected initiator is not an authorized officer.'),
        ];
    }
}
