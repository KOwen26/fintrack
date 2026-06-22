<?php

namespace App\Listeners;

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
}
