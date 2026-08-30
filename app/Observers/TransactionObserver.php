<?php

namespace App\Observers;

use App\Enums\TransactionType;
use App\Models\Transaction;

class TransactionObserver
{
    /**
     * The multiplier applied to the transaction amount based on its type.
     * +1 for inflows (increase balance), -1 for outflows (decrease balance).
     */
    private static function directionMultiplier(TransactionType | string $type): int
    {
        $value = $type instanceof TransactionType ? $type->value : $type;

        return in_array($value, TransactionType::inflows(), true) ? 1 : -1;
    }

    /**
     * Adjust the account balance when a transaction is created.
     */
    public function created(Transaction $transaction): void
    {
        if ($transaction->account_id === null) {
            return;
        }

        $multiplier = self::directionMultiplier($transaction->type->value);

        $transaction->account()->increment('current_balance', $multiplier * $transaction->amount);
    }

    /**
     * Adjust the account balance when a transaction is updated.
     *
     * Handles amount changes by computing the delta between old and new values.
     * Also handles type changes by reversing the old impact and applying the new.
     */
    public function updated(Transaction $transaction): void
    {
        if ($transaction->account_id === null) {
            return;
        }

        $originalAmount = $transaction->getOriginal('amount');
        $originalType = $transaction->getOriginal('type');

        $currentAmount = $transaction->amount;
        $currentType = $transaction->type->value;

        // Reverse the old impact
        $oldMultiplier = self::directionMultiplier($originalType);
        $transaction->account()->decrement('current_balance', $oldMultiplier * $originalAmount);

        // Apply the new impact
        $newMultiplier = self::directionMultiplier($currentType);
        $transaction->account()->increment('current_balance', $newMultiplier * $currentAmount);
    }

    /**
     * Reverse the account balance impact when a transaction is soft-deleted.
     */
    public function deleted(Transaction $transaction): void
    {
        if ($transaction->account_id === null) {
            return;
        }

        $multiplier = self::directionMultiplier($transaction->type->value);

        // Reverse: decrement what was incremented, increment what was decremented
        $transaction->account()->decrement('current_balance', $multiplier * $transaction->amount);
    }

    /**
     * Re-apply the account balance impact when a soft-deleted transaction is restored.
     */
    public function restored(Transaction $transaction): void
    {
        $this->created($transaction);
    }
}
