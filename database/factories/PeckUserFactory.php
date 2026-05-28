<?php

namespace Database\Factories;

use App\Models\PeckUser;
use App\Models\PeckUserData;
use Illuminate\Database\Eloquent\Factories\Factory;

class PeckUserFactory extends Factory
{
    public function definition(): array
    {
        $userData = PeckUserData::factory()->create();

        return [
            'gaijin_id' => fake()->unique()->numberBetween(100000, 999999999),
            'username' => fake()->unique()->userName(),
            'discord_id' => $userData->discord_id,
            'status' => fake()->randomElement(PeckUser::STATUSES),
            'joindate' => fake()->optional()->dateTimeBetween('-2 years', 'now'),
            'initiator' => null,
        ];
    }
}
