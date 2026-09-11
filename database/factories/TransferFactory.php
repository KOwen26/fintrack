<?php

namespace Database\Factories;

use App\Models\Transfer;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Transfer>
 */
class TransferFactory extends Factory
{
    public function definition(): array
    {
        return [
            'created_by' => User::factory(),
            'amount' => fake()->randomFloat(2, 10_000, 5_000_000),
            'fee_amount' => null,
            'transaction_date' => fake()->dateTimeBetween('-1 year', 'now')->format('Y-m-d'),
            'description' => fake()->optional(0.6)->sentence(),
        ];
    }

    public function withFee(): static
    {
        return $this->state(['fee_amount' => fake()->randomFloat(2, 1_000, 10_000)]);
    }
}
