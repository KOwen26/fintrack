<?php

namespace Database\Factories;

use App\Enums\AccountAccessType;
use App\Enums\AccountType;
use App\Models\Account;
use App\Models\DecorationColor;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Account>
 */
class AccountFactory extends Factory
{
    public function definition(): array
    {
        return [
            'owner_id' => User::factory(),
            'provider_id' => null,
            'name' => fake()->words(2, true),
            'type' => AccountType::DebitAccount->value,
            'access_type' => AccountAccessType::Personal->value,
            'initial_balance' => 0,
            'credit_card_limit' => null,
            'currency' => 'IDR',
            'decorations' => function (array $attributes): array {
                $color = DecorationColor::inRandomOrder()->first();

                return [
                    'icon' => ['id' => 'wallet-bold', 'value' => 'ph:wallet-bold'],
                    'color' => [
                        'id' => $color->slug,
                        'value' => $color->hex,
                        'text_color' => $color->text_color,
                    ],
                ];
            },
            'archived_at' => null,
        ];
    }

    public function creditCard(): static
    {
        return $this->state([
            'type' => AccountType::CreditCard->value,
            'credit_card_limit' => 5_000_000,
        ]);
    }

    public function archived(): static
    {
        return $this->state(['archived_at' => now()]);
    }
}
