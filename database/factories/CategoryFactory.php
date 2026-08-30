<?php

namespace Database\Factories;

use App\Enums\CategoryType;
use App\Models\Category;
use App\Models\DecorationColor;
use App\Models\DecorationIcon;
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
                'icon' => DecorationIcon::inRandomOrder()->first()?->slug ?? 'tag',
                'color' => DecorationColor::inRandomOrder()->first()?->slug ?? 'slate-500',
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
