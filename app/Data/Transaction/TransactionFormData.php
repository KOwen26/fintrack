<?php

namespace App\Data\Transaction;

use App\Enums\TransactionType;
use App\Models\Transaction;
use App\Models\Transfer;
use Carbon\CarbonInterface;
use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * Read-side form payload shared by the create and edit surfaces. Collapses
 * the two persistence shapes — a plain transaction row and a transfer unit —
 * into one source of truth, so the form never folds transfer members itself.
 *
 * `id` is always the SOURCE (outflow) row id: it doubles as the delete target
 * for both variants (destroy routes unit members to deleteUnit). `transfer_id`
 * marks a unit edit and is the aggregate key for PUT /transfers.
 */
#[TypeScript]
class TransactionFormData extends Data
{
    public function __construct(
        public ?int $id,

        public TransactionType $type,

        public ?float $amount,

        public CarbonInterface $transaction_date,

        public ?string $description,

        public ?int $account_id,

        public ?int $destination_account_id,

        public ?int $category_id,

        public ?float $fee_amount,

        public ?int $transfer_id,
    ) {}

    /** Create seed: today's date, the expense tab active, no values. */
    public static function defaultExpense(): self
    {
        return new self(
            id: null,
            type: TransactionType::Expense,
            amount: null,
            transaction_date: now(),
            description: null,
            account_id: null,
            destination_account_id: null,
            category_id: null,
            fee_amount: null,
            transfer_id: null,
        );
    }

    public static function fromTransaction(Transaction $transaction): self
    {
        return new self(
            id: $transaction->id,
            type: $transaction->type,
            amount: (float) $transaction->amount,
            transaction_date: $transaction->transaction_date,
            description: $transaction->description,
            account_id: $transaction->account_id,
            destination_account_id: null,
            category_id: $transaction->category_id,
            fee_amount: null,
            transfer_id: null,
        );
    }

    public static function fromTransfer(Transfer $transfer): self
    {
        $transfer->loadMissing(['sourceTransaction', 'destinationTransaction']);

        return new self(
            id: $transfer->sourceTransaction?->id,
            type: TransactionType::Transfer,
            amount: (float) $transfer->amount,
            transaction_date: $transfer->transaction_date,
            description: $transfer->description,
            account_id: $transfer->sourceTransaction?->account_id,
            destination_account_id: $transfer->destinationTransaction?->account_id,
            category_id: null,
            fee_amount: $transfer->fee_amount !== null ? (float) $transfer->fee_amount : null,
            transfer_id: $transfer->id,
        );
    }
}
