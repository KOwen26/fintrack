<?php

namespace Database\Factories;

use App\Enums\Cashflow;
use App\Enums\Category;
use App\Models\ImportBatch;
use App\Models\ImportRow;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<ImportRow> */
final class ImportRowFactory extends Factory
{
    public function definition(): array
    {
        $flow = fake()->randomElement(Cashflow::cases());

        return [
            'batch_id' => ImportBatch::factory(),
            'row_number' => fake()->unique()->numberBetween(1, 10000),
            'transaction_date' => fake()->dateTimeThisYear(),
            'description' => fake()->sentence(3),
            'amount' => fake()->randomFloat(2, 1000, 1000000),
            'flow' => $flow,
            'category_id' => $flow === Cashflow::Inflow ? Category::OtherIncome : Category::OtherExpenses,
            'is_included' => true,
            'is_duplicate' => false,
            'transfer_destination_account_id' => null,
            'fingerprint' => fake()->sha1(),
            'parse_errors' => null,
            'raw' => [],
        ];
    }

    public function excluded(): static
    {
        return $this->state(fn (): array => ['is_included' => false]);
    }

    public function duplicate(): static
    {
        return $this->state(fn (): array => ['is_duplicate' => true, 'is_included' => false]);
    }

    /** @param array<string, string> $errors */
    public function withErrors(array $errors): static
    {
        return $this->state(fn (): array => ['parse_errors' => $errors, 'is_included' => false]);
    }
}
