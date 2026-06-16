<?php

namespace App\Services;

use App\Enums\TransactionType;
use App\Events\TransactionDeleted;
use App\Events\TransactionSaved;
use App\Models\Account;
use App\Models\Transaction;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class TransactionService
{
    public function create(Account $account, User $creator, array $data): Transaction
    {
        $transaction = Transaction::create([
            'account_id' => $account->id,
            'created_by' => $creator->id,
            'amount' => $data['amount'],
            'type' => $data['type'],
            'transfer_link_id' => $data['transfer_link_id'] ?? null,
            'transaction_date' => $data['transaction_date'],
            'category_id' => $data['category_id'] ?? null,
            'description' => $data['description'] ?? null,
        ]);

        TransactionSaved::dispatch($transaction);

        return $transaction;
    }

    public function update(Transaction $transaction, array $data): Transaction
    {
        $transaction->update([
            'amount' => $data['amount'],
            'transaction_date' => $data['transaction_date'],
            'category_id' => $data['category_id'] ?? null,
            'description' => $data['description'] ?? null,
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

    public function createTransfer(
        Account $sourceAccount,
        Account $destinationAccount,
        User $creator,
        float $amount,
        string $transactionDate,
        ?float $feeAmount,
        ?string $description
    ): Transaction {
        $linkId = (string) Str::uuid();

        return DB::transaction(function () use (
            $sourceAccount,
            $destinationAccount,
            $creator,
            $amount,
            $transactionDate,
            $feeAmount,
            $description,
            $linkId,
        ): Transaction {
            $outflow = $this->create($sourceAccount, $creator, [
                'amount' => $amount,
                'type' => TransactionType::TransferOut->value,
                'transfer_link_id' => $linkId,
                'transaction_date' => $transactionDate,
                'description' => $description,
            ]);

            $this->create($destinationAccount, $creator, [
                'amount' => $amount,
                'type' => TransactionType::TransferIn->value,
                'transfer_link_id' => $linkId,
                'transaction_date' => $transactionDate,
                'description' => $description,
            ]);

            if ($feeAmount !== null && $feeAmount > 0) {
                $this->create($sourceAccount, $creator, [
                    'amount' => $feeAmount,
                    'type' => TransactionType::Fee->value,
                    'transfer_link_id' => $linkId,
                    'transaction_date' => $transactionDate,
                    'description' => 'Transfer fee',
                ]);
            }

            return $outflow;
        });
    }
}
