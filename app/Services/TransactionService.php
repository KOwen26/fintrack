<?php

namespace App\Services;

use App\Data\Transaction\TransactionData;
use App\Enums\Cashflow;
use App\Enums\Category;
use App\Enums\DatePeriodPreset;
use App\Enums\TransactionType;
use App\Events\TransactionDeleted;
use App\Events\TransactionSaved;
use App\Models\Account;
use App\Models\Transaction;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Transaction mutation surface (create/update/delete with their events) plus
 * the two scoped windows (account, category). The global list's read
 * subsystem — search, sort, keyset pages, summary — lives in
 * TransactionListService.
 */
final class TransactionService
{
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
