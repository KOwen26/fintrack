<?php

namespace Database\Factories;

use App\Enums\TransactionFlow;
use App\Enums\TransactionType;
use App\Models\Account;
use App\Models\Category;
use App\Models\Transaction;
use App\Models\Transfer;
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
            'flow' => TransactionFlow::Outflow,
            'transfer_id' => null,
            'transaction_date' => fake()->dateTimeBetween('-1 year', 'now')->format('Y-m-d'),
            'description' => fake()->optional(0.6)->sentence(),
        ];
    }

    public function income(): static
    {
        return $this->state([
            'type' => TransactionType::Income,
            'flow' => TransactionFlow::Inflow,
        ]);
    }

    public function expense(): static
    {
        return $this->state([
            'type' => TransactionType::Expense,
            'flow' => TransactionFlow::Outflow,
        ]);
    }

    /** Outflow member row of a transfer unit — booked on the source account. */
    public function transferOutflow(Transfer | int $transfer): static
    {
        return $this->state([
            'type' => TransactionType::Transfer,
            'flow' => TransactionFlow::Outflow,
            'transfer_id' => $transfer instanceof Transfer ? $transfer->id : $transfer,
            'category_id' => null,
        ]);
    }

    /** Inflow member row of a transfer unit — booked on the destination account. */
    public function transferInflow(Transfer | int $transfer): static
    {
        return $this->state([
            'type' => TransactionType::Transfer,
            'flow' => TransactionFlow::Inflow,
            'transfer_id' => $transfer instanceof Transfer ? $transfer->id : $transfer,
            'category_id' => null,
        ]);
    }

    /** Fee member row of a transfer unit — an expense on the source account. */
    public function transferFee(Transfer | int $transfer): static
    {
        return $this->state([
            'type' => TransactionType::Expense,
            'flow' => TransactionFlow::Outflow,
            'transfer_id' => $transfer instanceof Transfer ? $transfer->id : $transfer,
            'category_id' => null,
        ]);
    }

    public function forCategory(int $categoryId): static
    {
        return $this->state(['category_id' => $categoryId]);
    }
}
