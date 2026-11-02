<?php

namespace Database\Factories;

use App\Models\Account;
use App\Models\Transaction;
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
            'transaction_date' => fake()->dateTimeBetween('-1 year', 'now')->format('Y-m-d H:i:s'),
            'description' => fake()->optional(0.6)->sentence(),
        ];
    }

    public function withFee(): static
    {
        return $this->state(['fee_amount' => fake()->randomFloat(2, 1_000, 10_000)]);
    }

    /**
     * A complete unit — outflow + inflow member rows on the given accounts,
     * dated inside the default list window, creator-scoped to the aggregate,
     * amounts mirroring it. Books the fee row and mirrors it on the
     * aggregate's fee_amount when $fee is given. Tags land on the source row,
     * matching TransferService booking.
     */
    public function withMembers(Account $source, Account $destination, ?float $fee = null, array $sourceTags = []): static
    {
        // state() is immutable — it returns a new instance, so chain from it.
        $factory = $fee !== null ? $this->state(['fee_amount' => $fee]) : $this;

        return $factory->afterCreating(function (Transfer $transfer) use ($source, $destination, $fee, $sourceTags): void {
            $outflow = [
                'account_id' => $source->id,
                'created_by' => $transfer->created_by,
                'amount' => $transfer->amount,
            ];

            if ($sourceTags !== []) {
                $outflow['tags'] = $sourceTags;
            }

            Transaction::factory()->transferOutflow($transfer)->recent()->create($outflow);

            Transaction::factory()->transferInflow($transfer)->recent()->create([
                'account_id' => $destination->id,
                'created_by' => $transfer->created_by,
                'amount' => $transfer->amount,
            ]);

            if ($fee === null) {
                return;
            }

            Transaction::factory()->transferFee($transfer)->recent()->create([
                'account_id' => $source->id,
                'created_by' => $transfer->created_by,
                'amount' => $fee,
            ]);
        });
    }
}
