<?php

namespace Database\Factories;

use App\Enums\HouseholdMemberRole;
use App\Models\Household;
use App\Models\HouseholdMember;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<HouseholdMember>
 */
class HouseholdMemberFactory extends Factory
{
    public function definition(): array
    {
        return [
            'household_id' => Household::factory(),
            'user_id' => User::factory(),
            'role' => HouseholdMemberRole::Member->value,
            'joined_at' => now(),
            'created_at' => now(),
        ];
    }

    public function owner(): static
    {
        return $this->state(['role' => HouseholdMemberRole::Owner->value]);
    }

    public function pending(): static
    {
        return $this->state(['joined_at' => null]);
    }
}
