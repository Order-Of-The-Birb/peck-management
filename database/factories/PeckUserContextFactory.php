<?php

namespace Database\Factories;

use App\Models\PeckUser;
use App\Models\PeckUserContext;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<PeckUserContext>
 */
class PeckUserContextFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => PeckUser::factory(),
            'context_id' => 0,
            'type' => PeckUserContext::TYPE_MISC,
            'from_date' => null,
            'to_date' => null,
            'weekdays' => null,
            'month_day' => null,
            'comment' => fake()->sentence(),
        ];
    }

    public function onceAbsence(): static
    {
        return $this->state(fn (): array => [
            'type' => PeckUserContext::TYPE_ONCE_ABSENCE,
            'from_date' => now()->toDateString(),
            'to_date' => now()->addWeek()->toDateString(),
            'weekdays' => null,
            'month_day' => null,
            'comment' => null,
        ]);
    }

    public function recurringWeekdaysAbsence(): static
    {
        return $this->state(fn (): array => [
            'type' => PeckUserContext::TYPE_RECURRING_ABSENCE,
            'from_date' => null,
            'to_date' => null,
            'weekdays' => [0, 3, 6],
            'month_day' => null,
            'comment' => null,
        ]);
    }

    public function recurringMonthDayAbsence(): static
    {
        return $this->state(fn (): array => [
            'type' => PeckUserContext::TYPE_RECURRING_ABSENCE,
            'from_date' => null,
            'to_date' => null,
            'weekdays' => null,
            'month_day' => 26,
            'comment' => null,
        ]);
    }
}
