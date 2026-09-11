<?php

namespace App\Observers;

use App\Enums\TransactionFlow;
use App\Models\Account;
use App\Models\Transaction;

class TransactionObserver
{
    /**
     * The multiplier applied to the transaction amount based on its flow.
     * +1 for inflows (increase balance), -1 for outflows (decrease balance).
     */
    private static function directionMultiplier(Transaction $transaction): int
    {
        return $transaction->flow === TransactionFlow::Inflow ? 1 : -1;
    }

    /**
     * Adjust the account balance when a transaction is created.
     */
    public function created(Transaction $transaction): void
    {
        if ($transaction->account_id === null) {
            return;
        }

        $transaction->account()->increment('current_balance', self::directionMultiplier($transaction) * $transaction->amount);
    }

    /**
     * Adjust balances when a transaction is updated.
     *
     * The OLD impact is reversed against the ORIGINAL account (using
     * getOriginal) so that account changes move the impact instead of
     * duplicating it. The NEW impact lands on the current account.
     */
    public function updated(Transaction $transaction): void
    {
        $originalAccountId = $transaction->getOriginal('account_id');

        if ($originalAccountId !== null) {
            $originalAmount = (float) $transaction->getOriginal('amount');
            $originalFlow = TransactionFlow::from($transaction->getOriginal('flow'));
            $originalMultiplier = $originalFlow === TransactionFlow::Inflow ? 1 : -1;

            Account::whereKey($originalAccountId)
                ->decrement('current_balance', $originalMultiplier * $originalAmount);
        }

        if ($transaction->account_id !== null) {
            $transaction->account()->increment('current_balance', self::directionMultiplier($transaction) * $transaction->amount);
        }
    }

    /**
     * Reverse the account balance impact when a transaction is soft-deleted.
     */
    public function deleted(Transaction $transaction): void
    {
        if ($transaction->account_id === null) {
            return;
        }

        $transaction->account()->decrement('current_balance', self::directionMultiplier($transaction) * $transaction->amount);
    }

    /**
     * Re-apply the account balance impact when a soft-deleted transaction is restored.
     */
    public function restored(Transaction $transaction): void
    {
        $this->created($transaction);
    }
}
