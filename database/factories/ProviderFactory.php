<?php

namespace Database\Factories;

use App\Enums\ProviderStatus;
use App\Enums\ProviderType;
use App\Models\Provider;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Provider>
 */
class ProviderFactory extends Factory
{
    public function definition(): array
    {
        return [
            'name' => fake()->company(),
            'slug' => fake()->unique()->slug(2),
            'logo_url' => null,
            'type' => fake()->randomElement(ProviderType::cases())->value,
            'status' => ProviderStatus::Active->value,
            'decorations' => [
                'icon' => 'ph:building-bank-bold',
                'color' => fake()->hexColor(),
            ],
        ];
    }
}
