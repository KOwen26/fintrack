<?php

namespace Database\Factories;

use App\Models\Account;
use App\Models\Budget;
use App\Models\Category;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Budget>
 */
class BudgetFactory extends Factory
{
    public function definition(): array
    {
        $date = fake()->dateTimeBetween('-1 year', 'now');

        return [
            'account_id' => Account::factory(),
            'category_id' => Category::factory(),
            'limit_amount' => fake()->randomFloat(2, 100_000, 5_000_000),
            'year' => (int) $date->format('Y'),
            'month' => (int) $date->format('n'),
        ];
    }

    public function forPeriod(int $year, int $month): static
    {
        return $this->state(['year' => $year, 'month' => $month]);
    }
}
