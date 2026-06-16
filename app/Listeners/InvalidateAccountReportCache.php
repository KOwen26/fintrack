<?php

namespace App\Listeners;

use App\Events\TransactionDeleted;
use App\Events\TransactionSaved;

class InvalidateAccountReportCache
{
    public function handle(TransactionSaved | TransactionDeleted $event): void
    {
        // Reserved for future report cache invalidation.
    }
}
