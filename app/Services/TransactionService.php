<?php

namespace App\Services;

use App\Data\Transaction\TransactionData;
use App\Data\Transaction\TransactionListData;
use App\Enums\Cashflow;
use App\Enums\Category;
use App\Enums\DatePeriodPreset;
use App\Enums\TransactionType;
use App\Events\TransactionDeleted;
use App\Events\TransactionSaved;
use App\Models\Account;
use App\Models\Transaction;
use App\Models\User;
use App\Objects\DatePeriod;
use App\Objects\TransactionCursor;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

final class TransactionService
{
    /** Rows fetched per "load more" request from the transaction index. */
    public const int PAGE_SIZE = 60;

    /**
     * Global list: one row per transfer unit (the outflow row) plus plain
     * rows and fee rows — inflow rows are hidden.
     */
    public static function getTransactions(User $user, DatePeriodPreset $period = DatePeriodPreset::Last6Months): array
    {
        return TransactionListData::collectFromTransactions(self::listQuery($user, $period->toPeriod())->get());
    }

    /**
     * One keyset page of the global list — same rows as getTransactions(),
     * capped at PAGE_SIZE after the cursor, with the total matching rows in
     * the period and whether further rows exist past the fetched page.
     *
     * @return array{rows: list<array<string, mixed>>, total: int, has_more: bool}
     */
    public static function getTransactionsPage(User $user, ?TransactionCursor $cursor, DatePeriodPreset $period = DatePeriodPreset::Last6Months): array
    {
        $query = self::listQuery($user, $period->toPeriod());

        if ($cursor !== null) {
            $query->where(function (Builder $query) use ($cursor): void {
                $query->where('transaction_date', '<', $cursor->date)
                    ->orWhere(function (Builder $query) use ($cursor): void {
                        $query->where('transaction_date', $cursor->date)
                            ->where('id', '<', $cursor->id);
                    });
            });
        }

        $transactions = $query->limit(self::PAGE_SIZE + 1)->get();
        $hasMore = $transactions->count() > self::PAGE_SIZE;

        return [
            'rows' => TransactionListData::collectFromTransactions($transactions->take(self::PAGE_SIZE)),
            'total' => (int) self::baseQuery($user, $period->toPeriod())->count(),
            'has_more' => $hasMore,
        ];
    }

    /** The list's ownership, type and period filters; count targets use this alone. */
    private static function baseQuery(User $user, DatePeriod $period): Builder
    {
        return Transaction::query()
            ->where('created_by', $user->id)
            ->whereNot(fn ($query) => $query->where('type', TransactionType::Transfer)->where('flow', Cashflow::Inflow))
            ->withinPeriod($period);
    }

    /**
     * The fully-ordered, eagerly-loaded list query. Keyset tiebreaker on `id`
     * makes the newest-first order deterministic per (date, id) — the unit of
     * the cursor.
     */
    private static function listQuery(User $user, DatePeriod $period): Builder
    {
        return self::baseQuery($user, $period)
            ->with([
                'account',
                'transfer.destinationTransaction.account',
            ])
            ->latest('transaction_date')
            ->orderByDesc('id');
    }

    public static function getSummary(User $user, DatePeriodPreset $period = DatePeriodPreset::Last6Months): array
    {
        $row = Transaction::query()
            ->where('created_by', $user->id)
            ->whereNot(fn ($query) => $query->where('type', TransactionType::Transfer)->where('flow', Cashflow::Inflow))
            ->withinPeriod($period->toPeriod())
            ->selectRaw(
                'COALESCE(SUM(CASE WHEN type = ? THEN amount ELSE 0 END), 0) AS income, COALESCE(SUM(CASE WHEN type = ? THEN amount ELSE 0 END), 0) AS expense',
                [TransactionType::Income->value, TransactionType::Expense->value]
            )
            ->first();

        $income = (float) ($row->income ?? 0);
        $expense = (float) ($row->expense ?? 0);

        return ['income' => $income, 'expense' => $expense, 'net' => $income - $expense];
    }

    public static function getAccountTransactions(Account $account, DatePeriodPreset $period = DatePeriodPreset::YearOnYear): Collection
    {
        return Transaction::query()
            ->where('account_id', $account->id)
            ->whereNot(fn ($query) => $query->where('type', TransactionType::Transfer)->where('flow', Cashflow::Inflow))
            ->withinPeriod($period->toPeriod())
            ->with(['account', 'transfer.transactions.account'])
            ->latest('transaction_date')
            ->get();
    }

    public static function getCategoryTransactions(Category $category, DatePeriodPreset $period = DatePeriodPreset::YearOnYear): Collection
    {
        return Transaction::query()
            ->where('category_id', $category)
            ->whereNot(fn ($query) => $query->where('type', TransactionType::Transfer)->where('flow', Cashflow::Inflow))
            ->withinPeriod($period->toPeriod())
            ->with(['account', 'transfer.transactions.account'])
            ->latest('transaction_date')
            ->get();
    }

    public function create(User $creator, TransactionData $data): Transaction
    {
        $transaction = Transaction::create([
            'account_id' => $data->account_id,
            'created_by' => $creator->id,
            'amount' => $data->amount,
            'type' => $data->type,
            'flow' => $data->derivedFlow(),
            'transfer_id' => $data->transfer_id,
            'transaction_date' => $data->transaction_date,
            'category_id' => $data->category_id,
            'description' => $data->description,
        ]);

        TransactionSaved::dispatch($transaction);

        return $transaction;
    }

    /**
     * Plain-row edit — the controller guarantees the row is not a transfer
     * unit member (unit members are rejected with 422 upstream).
     */
    public function update(Transaction $transaction, TransactionData $data): Transaction
    {
        $transaction->update([
            'account_id' => $data->account_id,
            'type' => $data->type,
            'flow' => $data->derivedFlow(),
            'amount' => $data->amount,
            'transaction_date' => $data->transaction_date,
            'category_id' => $data->category_id,
            'description' => $data->description,
        ]);

        TransactionSaved::dispatch($transaction->fresh());

        return $transaction->fresh();
    }

    /**
     * Soft-delete a plain row. Transfer-unit members are routed to
     * TransferService::deleteUnit() by the controller.
     */
    public function softDelete(Transaction $transaction): void
    {
        DB::transaction(function () use ($transaction): void {
            $transaction->delete();
            TransactionDeleted::dispatch($transaction);
        });
    }
}
