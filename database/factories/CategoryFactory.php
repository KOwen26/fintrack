<?php

namespace Database\Factories;

use App\Enums\CategoryType;
use App\Models\Category;
use Database\Factories\Concerns\HasDecorations;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Category>
 */
class CategoryFactory extends Factory
{
    use HasDecorations;

    public function definition(): array
    {
        return [
            'parent_id' => null,
            'name' => fake()->word(),
            'type' => fake()->randomElement(CategoryType::cases()),
            'order' => 0.100,
            'decorations' => $this->randomDecorations(),
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

    public function withoutDecorations(): static
    {
        return $this->state(['decorations' => null]);
    }
}
