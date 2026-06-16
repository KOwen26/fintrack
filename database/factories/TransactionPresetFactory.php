<?php

namespace Database\Factories;

use App\Enums\TransactionPresetType;
use App\Models\Account;
use App\Models\Category;
use App\Models\TransactionPreset;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<TransactionPreset>
 */
class TransactionPresetFactory extends Factory
{
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'name' => fake()->words(2, true),
            'type' => TransactionPresetType::Expense->value,
            'default_amount' => fake()->randomFloat(2, 1000, 100000),
            'default_category_id' => Category::factory(),
            'default_source_account_id' => Account::factory(),
            'default_destination_account_id' => null,
            'default_transfer_fee' => null,
            'description' => fake()->optional(0.5)->sentence(),
        ];
    }

    public function income(): static
    {
        return $this->state([
            'type' => TransactionPresetType::Income->value,
            'default_destination_account_id' => Account::factory(),
            'default_source_account_id' => null,
        ]);
    }

    public function expense(): static
    {
        return $this->state([
            'type' => TransactionPresetType::Expense->value,
            'default_source_account_id' => Account::factory(),
            'default_destination_account_id' => null,
        ]);
    }

    public function transfer(): static
    {
        return $this->state([
            'type' => TransactionPresetType::Transfer->value,
            'default_source_account_id' => Account::factory(),
            'default_destination_account_id' => Account::factory(),
            'default_transfer_fee' => fake()->randomFloat(2, 0, 10000),
        ]);
    }
}
