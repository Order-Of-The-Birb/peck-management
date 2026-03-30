<?php

namespace Database\Factories;

use App\Models\ApiKey;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ApiKey>
 */
class ApiKeyFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $plainToken = ApiKey::generatePlainToken();

        return [
            'owner' => static fn (): int => User::query()->create([
                'name' => fake()->name(),
                'email' => fake()->unique()->safeEmail(),
                'password' => 'password',
            ])->id,
            'key' => ApiKey::hashToken($plainToken),
            'key_prefix' => ApiKey::prefixFromToken($plainToken),
        ];
    }
}
