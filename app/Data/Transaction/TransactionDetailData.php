<?php

namespace App\Data\Transaction;

use App\Enums\Cashflow;
use App\Enums\Category;
use App\Enums\TransactionType;
use App\Helpers\TypeScript\Attributes\TypeScriptModel;
use App\Models\Account;
use App\Models\Transaction;
use Carbon\CarbonInterface;
use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * Read-side detail payload. Mirrors the transfer folding used by
 * TransactionListData / TransactionFormData: a transfer movement row always
 * carries its counterpart account in `destination_account_*`, so consumers
 * never fold transfer members themselves.
 */
#[TypeScript]
class TransactionDetailData extends Data
{
    public function __construct(
        public int $id,

        public TransactionType $type,

        public Cashflow $flow,

        public ?int $transfer_id,

        public float $amount,

        public CarbonInterface $transaction_date,

        public ?string $description,

        public ?int $account_id,

        public ?int $destination_account_id,

        public ?Category $category_id,

        #[TypeScriptModel(Account::class)]
        public ?Account $account,

        #[TypeScriptModel(Account::class)]
        public ?Account $destination_account,
    ) {}

    public static function fromTransaction(Transaction $transaction): self
    {
        $transaction->loadMissing([
            'account',
            'transfer.sourceTransaction.account',
            'transfer.destinationTransaction.account',
        ]);

        $transfer = $transaction->getRelation('transfer');

        $counterpart = null;

        if ($transfer !== null && $transaction->type === TransactionType::Transfer) {
            $counterpart = $transaction->flow === Cashflow::Outflow
                ? $transfer->destinationTransaction
                : $transfer->sourceTransaction;
        }

        return new self(
            id: $transaction->id,
            type: $transaction->type,
            flow: $transaction->flow,
            transfer_id: $transaction->transfer_id,
            amount: (float) $transaction->amount,
            transaction_date: $transaction->transaction_date,
            description: $transaction->description,
            account_id: $transaction->account_id,
            destination_account_id: $counterpart?->account_id,
            category_id: $transaction->category_id,
            account: $transaction->relationLoaded('account') ? $transaction->account : null,
            destination_account: $counterpart?->account,
        );
    }
}
