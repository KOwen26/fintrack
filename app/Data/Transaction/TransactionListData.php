<?php

namespace App\Data\Transaction;

use App\Data\Category\CategoryData;
use App\Enums\Cashflow;
use App\Enums\Category;
use App\Enums\TransactionType;
use App\Helpers\TypeScript\Attributes\TypeScriptModel;
use App\Models\Account;
use App\Models\Transaction;
use Carbon\CarbonInterface;
use Illuminate\Support\Collection;
use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

#[TypeScript]
final class TransactionListData extends Data
{
    public function __construct(
        public int $id,

        public TransactionType $type,

        public Cashflow $flow,

        public int $amount,

        public ?string $description,

        public CarbonInterface $transaction_date,

        public ?Category $category_id,

        public ?CategoryData $category,

        public int $account_id,

        public ?int $transfer_id,

        public ?int $destination_account_id,

        #[TypeScriptModel(Account::class)]
        public ?Account $account,

        #[TypeScriptModel(Account::class)]
        public ?Account $destination_account,
    ) {}

    /**
     * Build from a transaction, folding the transfer counterpart through the
     * aggregate. Movement rows fold to their opposite-flow counterpart; fee
     * rows and plain rows fold to nothing (destination stays NULL). No DB
     * queries beyond loadMissing — eager-loaded callers skip it entirely.
     */
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

        $category = $transaction->category_id;

        return new self(
            id: $transaction->id,
            type: $transaction->type,
            flow: $transaction->flow,
            amount: $transaction->amount,
            description: $transaction->description,
            transaction_date: $transaction->transaction_date,
            category_id: $transaction->category_id,
            category: $category !== null ? CategoryData::fromEnum($category) : null,
            account_id: $transaction->account_id,
            transfer_id: $transaction->transfer_id,
            destination_account_id: $counterpart?->account_id,
            destination_account: $counterpart?->account,
            account: $transaction->relationLoaded('account') ? $transaction->account : null,
        );
    }

    public static function collectFromTransactions(Collection $transactions)
    {
        $uniqueCategories = $transactions->pluck('category_id')->unique();
        $categories = $uniqueCategories->mapWithKeys(fn ($category): array => [$category?->value => $category ? CategoryData::fromEnum($category) : null])->all();

        $transactions = $transactions->map(function ($transaction) use ($categories) {
            $destinationAccount = null;

            if ($transaction?->transfer_id) {
                $destinationAccount = $transaction->transfer->destinationTransaction->account;
            }

            return new self(
                id: $transaction->id,
                type: $transaction->type,
                flow: $transaction->flow,
                amount: $transaction->amount,
                description: $transaction->description,
                transaction_date: $transaction->transaction_date,
                category_id: $transaction?->category_id,
                category: $transaction?->category_id ? $categories[$transaction?->category_id?->value] : null,
                account_id: $transaction->account_id,
                account: $transaction->account,
                transfer_id: $transaction?->transfer_id,
                destination_account_id: $destinationAccount?->id,
                destination_account: $destinationAccount
            )->toArray();
        })->toArray();

        return $transactions;
    }

    /**
     * Manual single-row list mapping — the serialization twin of
     * `fromTransaction()` emitting the same wire shape the `#[TypeScript]`
     * contract promises, but hand-built instead of paying Spatie's reflected
     * construction and transformation pipeline, and pre-parsing embedded
     * accounts so `json_encode` never re-runs model `jsonSerialize` per
     * occurrence. Use `toListRows()` for batches — it shares pre-parsed
     * categories and accounts across rows. Folding semantics are identical;
     * counterpart resolution stays relation-based, no DB queries.
     *
     * @return array{
     *     id: int,
     *     type: TransactionType,
     *     flow: Cashflow,
     *     amount: float,
     *     description: ?string,
     *     transaction_date: string,
     *     category_id: ?string,
     *     category: ?array<string, mixed>,
     *     account_id: int,
     *     transfer_id: ?int,
     *     destination_account_id: ?int,
     *     account: ?array<string, mixed>,
     *     destination_account: ?array<string, mixed>,
     * }
     */
    public static function toListRow(Transaction $transaction): array
    {
        $category = $transaction->category_id;
        $counterpart = self::counterpartFor($transaction);

        return self::row(
            transaction: $transaction,
            categoryRow: $category !== null ? self::categoryRow($category) : null,
            accountRow: self::accountRow($transaction->account),
            destinationAccountRow: self::accountRow($counterpart?->account),
            counterpart: $counterpart,
        );
    }

    /**
     * Batch mapping: each unique category and account is converted to its
     * wire form once and the row is assembled from those. Rows must have
     * `account` and `transfer` counterparts preloaded (`getTransactions()`
     * does) — bare rows would lazy-load one query per relation per row.
     *
     * @return list<array<string, mixed>>
     */
    public static function toListRows(Collection $transactions): array
    {
        $categoryRows = collect();
        $accountRows = collect();
        $accountRow = static fn (Account $account): array => ($accountRows[$account->id] ??= self::accountRow($account));

        return $transactions
            ->map(function (Transaction $transaction) use ($categoryRows, $accountRow): array {
                $counterpart = self::counterpartFor($transaction);
                $category = $transaction->category_id;

                return self::row(
                    transaction: $transaction,
                    categoryRow: $category !== null
                        ? ($categoryRows[$category->value] ??= self::categoryRow($category))
                        : null,
                    accountRow: $accountRow($transaction->account),
                    destinationAccountRow: $counterpart !== null
                        ? $accountRow($counterpart->account)
                        : null,
                    counterpart: $counterpart,
                );
            })
            ->all();
    }

    /**
     * Fold a row to its transfer-unit counterpart per fromTransaction():
     * movement rows yield the opposite-flow row; fee and plain rows none.
     */
    private static function counterpartFor(Transaction $transaction): ?Transaction
    {
        $transfer = $transaction->getRelation('transfer');

        if ($transfer !== null && $transaction->type === TransactionType::Transfer) {
            return $transaction->flow === Cashflow::Outflow
                ? $transfer->destinationTransaction
                : $transfer->sourceTransaction;
        }

        return null;
    }

    /** Account wire form — its full attribute array, parsed once. */
    private static function accountRow(?Account $account): ?array
    {
        return $account !== null ? json_decode($account->toJson(), true) : null;
    }

    /**
     * @param  ?array<string, mixed>  $categoryRow
     * @param  ?array<string, mixed>  $accountRow
     * @param  ?array<string, mixed>  $destinationAccountRow
     *
     * @return array{
     *     id: int,
     *     type: TransactionType,
     *     flow: Cashflow,
     *     amount: float,
     *     description: ?string,
     *     transaction_date: string,
     *     category_id: ?string,
     *     category: ?array<string, mixed>,
     *     account_id: int,
     *     transfer_id: ?int,
     *     destination_account_id: ?int,
     *     account: ?array<string, mixed>,
     *     destination_account: ?array<string, mixed>,
     * }
     */
    private static function row(
        Transaction $transaction,
        ?array $categoryRow,
        ?array $accountRow,
        ?array $destinationAccountRow,
        ?Transaction $counterpart,
    ): array {
        return [
            'id' => $transaction->id,
            'type' => $transaction->type,
            'flow' => $transaction->flow,
            'amount' => $transaction->amount,
            'description' => $transaction->description,
            'transaction_date' => $transaction->transaction_date->toIso8601String(),
            'category_id' => $transaction->category_id?->value,
            'category' => $categoryRow,
            'account_id' => $transaction->account_id,
            'transfer_id' => $transaction->transfer_id,
            'destination_account_id' => $counterpart?->account_id,
            'destination_account' => $destinationAccountRow,
            'account' => $accountRow,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private static function categoryRow(Category $category): array
    {
        $group = $category->group();

        return [
            'id' => $category->value,
            'name' => $category->label(),
            'cashflow' => $category->group()->flow(),
            'decorations' => $category->decorations(),
            'group' => [
                'id' => $group->value,
                'name' => $group->label(),
                'cashflow' => $group->flow(),
                'decorations' => $group->decorations(),
            ],
        ];
    }
}
