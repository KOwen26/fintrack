<?php

namespace App\Listeners;

use App\Events\TransactionDeleted;
use App\Events\TransactionSaved;
use App\Services\TransactionListService;
use Illuminate\Support\Facades\Cache;

/**
 * Flushes the cached transaction lists of the affected user — one cache
 * entry per user, so one write is one exact Cache::forget. Every
 * transaction write funnels through TransactionSaved / TransactionDeleted:
 * plain CRUD, transfer unit create/delete, and the account initial-balance
 * sync all dispatch them.
 */
final class FlushTransactionListCache
{
    public function handle(TransactionSaved | TransactionDeleted $event): void
    {
        Cache::forget(TransactionListService::cacheKey($event->transaction->created_by));
    }
}
