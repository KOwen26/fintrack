<?php

namespace App\Services;

use App\Data\Transaction\TransactionData;
use App\Enums\TransactionType;
use App\Events\TransactionDeleted;
use App\Events\TransactionSaved;
use App\Models\Account;
use App\Models\Category;
use App\Models\Transaction;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class TransactionService
{
    public static function getTransactions(User $user): Collection
    {
        return Transaction::query()
            ->where('created_by', $user->id)
            ->with(['account', 'category', 'relatedTransaction.account'])
            ->latest('transaction_date')
            ->get();
    }

    public static function getAccountTransactions(Account $account): Collection
    {
        return Transaction::query()
            ->where('account_id', $account->id)
            ->with(['account', 'category'])
            ->latest('transaction_date')
            ->get();
    }

    public static function getCategoryTransactions(Category $category): Collection
    {
        return Transaction::query()
            ->where('category_id', $category->id)
            ->with(['account', 'category'])
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
            'transfer_link_id' => $data->transfer_link_id,
            'transaction_date' => $data->transaction_date,
            'category_id' => $data->category_id,
            'description' => $data->description,
        ]);

        TransactionSaved::dispatch($transaction);

        return $transaction;
    }

    public function update(Transaction $transaction, TransactionData $data): Transaction
    {
        $transaction->update([
            'account_id' => $data->account_id,
            'type' => $data->type,
            'amount' => $data->amount,
            'transaction_date' => $data->transaction_date,
            'category_id' => $data->category_id,
            'description' => $data->description,
        ]);

        TransactionSaved::dispatch($transaction->fresh());

        return $transaction->fresh();
    }

    public function softDelete(Transaction $transaction): void
    {
        if ($transaction->transfer_link_id) {
            $linked = Transaction::where('transfer_link_id', $transaction->transfer_link_id)->get();

            foreach ($linked as $linked_tx) {
                $linked_tx->delete();
                TransactionDeleted::dispatch($linked_tx);
            }

            return;
        }

        $transaction->delete();
        TransactionDeleted::dispatch($transaction);
    }

    public function createTransfer(User $creator, TransactionData $data): Transaction
    {
        $sourceAccount = Account::findOrFail($data->account_id);
        $destinationAccount = Account::findOrFail($data->destination_account_id);
        $linkId = (string) Str::uuid();

        return DB::transaction(function () use (
            $creator,
            $sourceAccount,
            $destinationAccount,
            $data,
            $linkId,
        ): Transaction {
            $outflow = $this->create($creator, new TransactionData(
                account_id: $sourceAccount->id,
                type: TransactionType::TransferOut->value,
                amount: $data->amount,
                transaction_date: $data->transaction_date,
                description: $data->description,
                transfer_link_id: $linkId,
            ));

            $this->create($creator, new TransactionData(
                account_id: $destinationAccount->id,
                type: TransactionType::TransferIn->value,
                amount: $data->amount,
                transaction_date: $data->transaction_date,
                description: $data->description,
                transfer_link_id: $linkId,
            ));

            if ($data->fee_amount !== null && $data->fee_amount > 0) {
                $this->create($creator, new TransactionData(
                    account_id: $sourceAccount->id,
                    type: TransactionType::Expense->value,
                    amount: $data->fee_amount,
                    transaction_date: $data->transaction_date,
                    category_id: $this->resolveTransferFeeCategory(),
                    description: 'Transfer fee',
                    transfer_link_id: $linkId,
                ));
            }

            return $outflow;
        });
    }

    /**
     * Resolve the Admin Fees child category for booking transfer fees.
     * Returns null when it does not exist — the fee then books as uncategorized.
     */
    private function resolveTransferFeeCategory(): ?int
    {
        return Category::query()
            ->where('name', 'Admin Fees')
            ->levelChildren()
            ->first()
            ?->id;
    }
}
