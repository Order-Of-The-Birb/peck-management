<?php

namespace App\Http\Requests;

use App\Models\PeckUser;
use Closure;
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
        /** @var PeckUser $peckUser */
        $peckUser = $this->route('peckUser');

        return [
            'username' => [
                'sometimes',
                'required',
                'string',
                'max:255',
                Rule::unique('peck_users', 'username')->ignore($peckUser->gaijin_id, 'gaijin_id'),
            ],
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
            'joindate' => [
                'sometimes',
                'nullable',
                'date_format:Y-m-d',
            ],
            'initiator' => [
                'sometimes',
                'nullable',
                'integer',
                Rule::exists('officers', 'gaijin_id'),
                function (string $attribute, mixed $value, Closure $fail) use ($peckUser): void {
                    if ($value !== null && (int) $value === $peckUser->gaijin_id) {
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
            'username.required' => __('A username is required.'),
            'username.unique' => __('That username is already in use.'),
            'status.in' => __('The selected status is invalid.'),
            'tz.between' => __('The timezone must be between -11 and 12.'),
            'initiator.exists' => __('The selected initiator is not an authorized officer.'),
        ];
    }
}
