<?php

namespace Database\Factories;

use App\Enums\ProviderStatus;
use App\Enums\ProviderType;
use App\Models\DecorationColor;
use App\Models\DecorationIcon;
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
                'icon' => DecorationIcon::inRandomOrder()->first()?->slug ?? 'building-bank-bold',
                'color' => DecorationColor::inRandomOrder()->first()?->slug ?? 'slate-500',
            ],
        ];
    }
}
