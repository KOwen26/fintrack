<?php

namespace Database\Factories;

use App\Models\Household;
use App\Models\HouseholdInvitation;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<HouseholdInvitation>
 */
class HouseholdInvitationFactory extends Factory
{
    public function definition(): array
    {
        return [
            'household_id' => Household::factory(),
            'invited_by' => User::factory(),
            'email' => fake()->safeEmail(),
            'token' => Str::random(64),
            'accepted_at' => null,
            'expires_at' => now()->addHours(48),
            'created_at' => now(),
        ];
    }

    public function expired(): static
    {
        return $this->state(['expires_at' => now()->subHour()]);
    }

    public function accepted(): static
    {
        return $this->state(['accepted_at' => now()->subMinutes(10)]);
    }
}
