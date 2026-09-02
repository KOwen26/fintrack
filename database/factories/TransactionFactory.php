<?php

namespace Database\Factories;

use App\Enums\TransactionType;
use App\Models\Account;
use App\Models\Category;
use App\Models\Transaction;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Transaction>
 */
class TransactionFactory extends Factory
{
    public function definition(): array
    {
        return [
            'account_id' => Account::factory(),
            'category_id' => Category::factory(),
            'created_by' => User::factory(),
            'amount' => fake()->randomFloat(2, 1_000_000, 10_000_000),
            'type' => TransactionType::Expense,
            'transfer_link_id' => null,
            'transaction_date' => fake()->dateTimeBetween('-1 year', 'now')->format('Y-m-d'),
            'description' => fake()->optional(0.6)->sentence(),
        ];
    }

    public function income(): static
    {
        return $this->state(['type' => TransactionType::Income]);
    }

    public function expense(): static
    {
        return $this->state(['type' => TransactionType::Expense]);
    }

    public function transferOut(string $linkId): static
    {
        return $this->state([
            'type' => TransactionType::TransferOut,
            'transfer_link_id' => $linkId,
        ]);
    }

    public function transferIn(string $linkId): static
    {
        return $this->state([
            'type' => TransactionType::TransferIn,
            'transfer_link_id' => $linkId,
        ]);
    }

    public function fee(string $linkId): static
    {
        return $this->state([
            'type' => TransactionType::Fee,
            'transfer_link_id' => $linkId,
        ]);
    }

    public function forCategory(int $categoryId): static
    {
        return $this->state(['category_id' => $categoryId]);
    }
}
