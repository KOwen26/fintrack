<?php

namespace Database\Factories;

use App\Enums\RecurringFrequency;
use App\Enums\TransactionPresetType;
use App\Models\Account;
use App\Models\Category;
use App\Models\TransactionRecurringPreset;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<TransactionRecurringPreset>
 */
class TransactionRecurringPresetFactory extends Factory
{
    public function definition(): array
    {
        return [
            'account_id' => Account::factory(),
            'category_id' => Category::factory(),
            'created_by' => User::factory(),
            'name' => fake()->words(2, true),
            'type' => TransactionPresetType::Expense->value,
            'frequency' => RecurringFrequency::Monthly->value,
            'amount' => fake()->randomFloat(2, 1000, 100000),
            'next_run_date' => fake()->dateTimeBetween('now', '+1 month')->format('Y-m-d'),
            'recurrence_end_date' => null,
            'last_run_date' => null,
            'is_active' => true,
            'description' => fake()->optional(0.5)->sentence(),
        ];
    }

    public function due(): static
    {
        return $this->state([
            'next_run_date' => today()->subDays(fake()->numberBetween(0, 10)),
            'is_active' => true,
        ]);
    }

    public function overdue(): static
    {
        return $this->state([
            'next_run_date' => today()->subDays(fake()->numberBetween(1, 30)),
            'is_active' => true,
        ]);
    }

    public function inactive(): static
    {
        return $this->state([
            'is_active' => false,
        ]);
    }

    public function withEndDate(string $date): static
    {
        return $this->state([
            'recurrence_end_date' => $date,
        ]);
    }

    public function monthly(): static
    {
        return $this->state([
            'frequency' => RecurringFrequency::Monthly->value,
        ]);
    }
}
