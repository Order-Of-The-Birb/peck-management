<?php

namespace Database\Factories;

use App\Models\PeckAlt;
use App\Models\PeckUser;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<PeckAlt>
 */
class PeckAltFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $ownerUser = PeckUser::factory()->create();
        $altUser = PeckUser::factory()->create();

        return [
            'alt_id' => $altUser->gaijin_id,
            'owner_id' => $ownerUser->gaijin_id,
        ];
    }
}
