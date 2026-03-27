<?php

namespace Database\Factories;

use App\Models\Officer;
use App\Models\PeckUser;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Officer>
 */
class OfficerFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $peckUser = PeckUser::factory()->create();

        return [
            'gaijin_id' => $peckUser->gaijin_id,
            'rank' => fake()->optional()->randomElement([
                'Commander',
                'Executive Officer',
                'Recruitment Officer',
            ]),
        ];
    }
}
