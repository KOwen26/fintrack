<?php

namespace App\Data\Transaction;

use App\Enums\TransactionFlow;
use App\Enums\TransactionType;
use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * Write-side payload for creating or updating a PLAIN transaction (income or
 * expense) — transfer units use TransferData via the /transfers endpoints.
 * `flow` is derived server-side from `type`; `transfer_id` is set internally
 * for unit member rows. Neither is ever accepted from clients.
 */
#[TypeScript]
class TransactionData extends Data
{
    public function __construct(
        public int $account_id,

        public TransactionType $type,

        public float $amount,

        public string $transaction_date,

        /** Income/expense always carry one (form-enforced). */
        public ?int $category_id = null,

        public ?string $description = null,

        /** Service-internal only — derived from type, never client-sent. */
        public ?TransactionFlow $flow = null,

        /** Service-internal only — set for unit member rows, never client-sent. */
        public ?int $transfer_id = null,
    ) {}

    public function derivedFlow(): TransactionFlow
    {
        return $this->flow
            ?? ($this->type === TransactionType::Income ? TransactionFlow::Inflow : TransactionFlow::Outflow);
    }
}
