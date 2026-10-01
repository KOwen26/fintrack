<?php

namespace App\Data\Transaction;

use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * Write-side payload for creating or updating a transfer UNIT via the
 * /transfers endpoints. No type/flow — the endpoint implies them and the
 * service derives per-row values. The payload covers all member rows
 * including the optional fee.
 */
#[TypeScript]
class TransferData extends Data
{
    public function __construct(
        public int $account_id,

        public int $destination_account_id,

        public float $amount,

        public string $transaction_date,

        public ?float $fee_amount = null,

        public ?string $description = null,
    ) {}
}
