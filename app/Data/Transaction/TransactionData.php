<?php

namespace App\Data\Transaction;

use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * Write-side payload for creating or updating a transaction, normalized from
 * SaveTransactionRequest::validated(). `type` keeps the raw form value —
 * 'transfer' is a UI pseudo-type that the service expands into the
 * TransferOut/TransferIn pair (plus the optional fee row), so it is
 * deliberately not a TransactionType here.
 */
#[TypeScript]
class TransactionData extends Data
{
    public function __construct(
        public int $account_id,

        public string $type,

        public float $amount,

        public string $transaction_date,

        /** Income/expense always carry one (form-enforced); transfer legs don't. */
        public ?int $category_id = null,

        public ?string $description = null,

        public ?int $destination_account_id = null,

        public ?float $fee_amount = null,

        /**
         * Service-internal only: set by createTransfer() to link the legs.
         * Never part of the request payload.
         */
        public ?string $transfer_link_id = null,
    ) {}

    public function isTransfer(): bool
    {
        return $this->type === 'transfer';
    }
}
