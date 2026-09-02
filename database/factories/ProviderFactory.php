<?php

namespace Database\Factories;

use App\Enums\ProviderStatus;
use App\Enums\ProviderType;
use App\Models\Provider;
use Database\Factories\Concerns\HasDecorations;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Provider>
 */
class ProviderFactory extends Factory
{
    use HasDecorations;

    public function definition(): array
    {
        return [
            'name' => fake()->company(),
            'slug' => fake()->unique()->slug(2),
            'logo_url' => null,
            'type' => fake()->randomElement(ProviderType::cases()),
            'status' => ProviderStatus::Active,
            'decorations' => $this->randomDecorations(),
        ];
    }

    public function withoutDecorations(): static
    {
        return $this->state(['decorations' => null]);
    }
}
