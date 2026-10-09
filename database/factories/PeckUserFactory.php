<?php

namespace Database\Factories;

use App\Models\PeckUserData;
use Illuminate\Database\Eloquent\Factories\Factory;

class PeckUserFactory extends Factory
{
    public function definition(): array
    {
        $userData = PeckUserData::factory()->create();

        return [
            'gaijin_id' => fake()->unique()->numberBetween(100000, 999999999),
            'discord_id' => $userData->discord_id,
        ];
    }
}
