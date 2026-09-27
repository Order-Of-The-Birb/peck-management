<?php

namespace Database\Factories;

use App\Models\ThunderApiToken;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<ThunderApiToken>
 */
class ThunderApiTokenFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'token' => Str::random(64),
            'expires_at' => now()->addDay()->timestamp,
            'refreshed_at' => now(),
        ];
    }

    public function expired(): static
    {
        return $this->state(fn (array $attributes): array => [
            'expires_at' => now()->subDay()->timestamp,
        ]);
    }
}
