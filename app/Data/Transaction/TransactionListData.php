<?php

namespace App\Data\Transaction;

use App\Enums\TransactionType;
use App\Helpers\TypeScript\Attributes\TypeScriptModel;
use App\Models\Account;
use App\Models\Category;
use App\Models\Transaction;
use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

#[TypeScript]
class TransactionListData extends Data
{
    public function __construct(
        public int $id,

        public TransactionType $type,

        public float $amount,

        public ?string $description,

        public string $transaction_date,

        public ?int $category_id,

        public int $account_id,

        public ?string $transfer_link_id,

        public ?int $destination_account_id,

        #[TypeScriptModel(Transaction::class)]
        public ?Transaction $related_transaction,

        #[TypeScriptModel(Account::class)]
        public ?Account $account,

        #[TypeScriptModel(Category::class)]
        public ?Category $category,
    ) {}

    /**
     * Build from a transaction, mapping the transfer counterpart from the
     * relatedTransaction relation. No DB queries here (standing rule:
     * relational loading, mapping, filtering only) — `loadMissing` covers
     * ad-hoc callers; eager-loaded callers skip it entirely.
     */
    public static function fromTransaction(Transaction $transaction): self
    {
        $related = $transaction->loadMissing('relatedTransaction')->getRelation('relatedTransaction');

        return new self(
            id: $transaction->id,
            type: $transaction->type,
            amount: (float) $transaction->amount,
            description: $transaction->description,
            transaction_date: $transaction->transaction_date->toDateString(),
            category_id: $transaction->category_id,
            account_id: $transaction->account_id,
            transfer_link_id: $transaction->transfer_link_id,
            destination_account_id: $related?->account_id,
            related_transaction: $related,
            account: $transaction->relationLoaded('account') ? $transaction->account : null,
            category: $transaction->relationLoaded('category') ? $transaction->category : null,
        );
    }
}
