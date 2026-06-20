<?php

namespace Database\Factories;

use App\Enums\CategoryType;
use App\Models\Category;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Category>
 */
class CategoryFactory extends Factory
{
    public function definition(): array
    {
        return [
            'parent_id' => null,
            'name' => fake()->word(),
            'type' => fake()->randomElement(CategoryType::cases()),
            'order' => 0.100,
            'decorations' => [
                'icon' => ['id' => 'tag', 'value' => 'ph:tag'],
                'color' => ['id' => fake()->hexColor(), 'value' => fake()->hexColor()],
            ],
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
