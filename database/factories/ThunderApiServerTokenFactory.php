<?php

namespace Database\Factories;

use App\Models\ThunderApiServerToken;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<ThunderApiServerToken>
 */
class ThunderApiServerTokenFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'token' => Str::random(64),
            'gaijin_id' => null,
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
