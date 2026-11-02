<?php

namespace Database\Factories;

use App\Enums\Category;
use App\Enums\TransactionType;
use App\Models\Account;
use App\Models\TransactionTemplate;
use App\Models\User;
use Database\Factories\Concerns\HasDecorations;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<TransactionTemplate>
 */
class TransactionTemplateFactory extends Factory
{
    use HasDecorations;

    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'account_id' => null,
            'name' => fake()->words(2, true),
            'type' => TransactionType::Expense,
            'category_id' => Category::DiningOut,
            'amount' => 25_000,
            'destination_account_id' => null,
            'fee_amount' => null,
            'description' => null,
            'decorations' => $this->randomDecorations(),
        ];
    }

    public function income(): static
    {
        return $this->state([
            'type' => TransactionType::Income,
            'category_id' => Category::Salary,
            'amount' => 5_000_000,
        ]);
    }

    public function transfer(): static
    {
        return $this->state([
            'type' => TransactionType::Transfer,
            'category_id' => null,
            'destination_account_id' => Account::factory(),
            'amount' => 1_000_000,
        ]);
    }
}
