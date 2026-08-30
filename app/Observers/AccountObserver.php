<?php

namespace App\Observers;

use App\Models\Account;

class AccountObserver
{
    /**
     * When an account is created, set current_balance to match initial_balance.
     */
    public function created(Account $account): void
    {
        if ($account->initial_balance != 0) {
            $account->updateQuietly(['current_balance' => $account->initial_balance]);
        }
    }

    /**
     * When an account's initial_balance changes, adjust current_balance by the delta.
     */
    public function updated(Account $account): void
    {
        if ($account->isDirty('initial_balance')) {
            $oldInitial = (float) $account->getOriginal('initial_balance');
            $newInitial = (float) $account->initial_balance;
            $delta = $newInitial - $oldInitial;

            $account->updateQuietly([
                'current_balance' => $account->current_balance + $delta,
            ]);
        }
    }
}
