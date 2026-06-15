<?php

namespace Database\Factories;

use App\Models\Category;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Category>
 */
class CategoryFactory extends Factory
{
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'parent_id' => null,
            'name' => fake()->word(),
            'icon' => 'ph:tag',
            'color' => fake()->hexColor(),
            'is_fixed_cost' => false,
        ];
    }

    public function child(int $parentId): static
    {
        return $this->state(['parent_id' => $parentId]);
    }

    public function fixed(): static
    {
        return $this->state(['is_fixed_cost' => true]);
    }
}
