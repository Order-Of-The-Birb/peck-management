<?php

namespace Database\Factories;

use App\Models\PeckUserData;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<PeckUserData>
 */
class PeckUserDataFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'discord_id' => fake()->unique()->numberBetween(100000000000000, 999999999999999999),
            'sqb_part' => fake()->boolean(),
            'timezone' => fake()->optional()->numberBetween(-11, 12),
        ];
    }
}
