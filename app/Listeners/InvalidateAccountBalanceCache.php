<?php

namespace App\Listeners;

use App\Events\RecurringPresetExecuted;
use App\Events\TransactionDeleted;
use App\Events\TransactionSaved;
use Illuminate\Support\Facades\Cache;

class InvalidateAccountBalanceCache
{
    public function handle(TransactionSaved | TransactionDeleted $event): void
    {
        $accountId = $event->transaction->account_id;

        Cache::tags(['account:' . $accountId])->flush();
    }

    /**
     * Handle RecurringPresetExecuted — invalidate the account balance cache
     * for the account that just received a new auto-generated transaction.
     */
    public function handleRecurringPresetExecuted(RecurringPresetExecuted $event): void
    {
        Cache::tags(['account:' . $event->preset->account_id])->flush();
    }
}
