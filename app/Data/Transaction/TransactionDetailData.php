<?php

namespace App\Data\Transaction;

use App\Enums\TransactionType;
use App\Helpers\TypeScript\Attributes\TypeScriptModel;
use App\Models\Account;
use App\Models\Category;
use App\Models\User;
use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

#[TypeScript]
class TransactionDetailData extends Data
{
    public function __construct(
        public int $id,

        public TransactionType $type,

        public float $amount,

        public string $description,

        public string $transaction_date,

        public string $created_at,

        public string $updated_at,

        #[TypeScriptModel(Account::class)]
        public mixed $account,

        #[TypeScriptModel(Category::class)]
        public mixed $category,

        #[TypeScriptModel(User::class)]
        public mixed $creator,
    ) {}
}
