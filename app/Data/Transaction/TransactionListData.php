<?php

namespace App\Data\Transaction;

use App\Enums\Category;
use App\Enums\TransactionFlow;
use App\Enums\TransactionType;
use App\Helpers\TypeScript\Attributes\TypeScriptModel;
use App\Models\Account;
use App\Models\Transaction;
use Carbon\CarbonInterface;
use Illuminate\Support\Collection;
use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

#[TypeScript]
class TransactionListData extends Data
{
    public function __construct(
        public int $id,

        public TransactionType $type,

        public TransactionFlow $flow,

        public float $amount,

        public ?string $description,

        public CarbonInterface $transaction_date,

        public ?Category $category_id,

        public int $account_id,

        public ?int $transfer_id,

        public ?int $destination_account_id,

        #[TypeScriptModel(Transaction::class)]
        public ?Transaction $related_transaction,

        #[TypeScriptModel(Account::class)]
        public ?Account $account,
    ) {}

    /**
     * Build from a transaction, folding the transfer counterpart through the
     * aggregate. Movement rows fold to their opposite-flow counterpart; fee
     * rows and plain rows fold to nothing (destination stays NULL). No DB
     * queries beyond loadMissing — eager-loaded callers skip it entirely.
     */
    public static function fromTransaction(Transaction $transaction): self
    {
        $transaction->loadMissing('transfer.transactions.account');

        $transfer = $transaction->getRelation('transfer');

        $counterpart = null;
        if ($transfer !== null && $transaction->type === TransactionType::Transfer) {
            $counterpart = $transfer->transactions
                ->first(fn (Transaction $member): bool => $member->id !== $transaction->id
                    && $member->flow !== $transaction->flow);
        }

        return new self(
            id: $transaction->id,
            type: $transaction->type,
            flow: $transaction->flow,
            amount: (float) $transaction->amount,
            description: $transaction->description,
            transaction_date: $transaction->transaction_date,
            category_id: $transaction->category_id,
            account_id: $transaction->account_id,
            transfer_id: $transaction->transfer_id,
            destination_account_id: $counterpart?->account_id,
            related_transaction: $counterpart,
            account: $transaction->relationLoaded('account') ? $transaction->account : null,
        );
    }

    public static function collectFromTransactions(Collection $transactions)
    {
        return self::collect($transactions->map(fn (Transaction $transaction): self => self::fromTransaction($transaction)));
    }
}
