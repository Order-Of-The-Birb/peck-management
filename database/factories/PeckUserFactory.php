<?php

namespace Database\Factories;

use App\Models\PeckUser;
use Illuminate\Database\Eloquent\Factories\Factory;

class PeckUserFactory extends Factory
{
    public function definition(): array
    {
        $gaijinId = fake()->unique()->numberBetween(100000, 999999999);

        return [
            'gaijin_id' => $gaijinId,
            'username' => fake()->unique()->userName(),
            'discord_id' => fake()->numberBetween(100000000000000, 999999999999999999),
            'tz' => fake()->optional()->numberBetween(-720, 840),
            'status' => fake()->randomElement(PeckUser::STATUSES),
            'joindate' => fake()->optional()->dateTimeBetween('-2 years', 'now'),
            'initiator' => null,
        ];
    }
}
