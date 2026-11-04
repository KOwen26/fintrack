<?php

namespace Database\Factories;

use App\Enums\ImportBatchStatus;
use App\Models\ImportBatch;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<ImportBatch> */
final class ImportBatchFactory extends Factory
{
    public function definition(): array
    {
        return [
            'created_by' => User::factory(),
            'account_id' => Account::factory(),
            'provider_id' => Provider::factory(),
            'source_filename' => 'mutasi.xlsx',
            'status' => ImportBatchStatus::Staging,
            'total_rows' => 0,
            'imported_rows' => 0,
            'approved_at' => null,
            'discarded_at' => null,
        ];
    }

    public function approved(): static
    {
        return $this->state(fn (): array => [
            'status' => ImportBatchStatus::Approved,
            'approved_at' => now(),
        ]);
    }

    public function discarded(): static
    {
        return $this->state(fn (): array => [
            'status' => ImportBatchStatus::Discarded,
            'discarded_at' => now(),
        ]);
    }
}
