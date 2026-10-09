<?php

namespace App\Services;

use App\Models\Account;
use App\Models\Transaction;

class BalanceService
{
    public function forAccount(Account $account): string
    {
        $balance = Transaction::query()
            ->where('account_id', $account->id)
            ->selectRaw("COALESCE(SUM(CASE WHEN flow = 'inflow' THEN amount ELSE -amount END), 0) AS balance")
            ->value('balance');

        return (string) ($balance ?? 0);
    }
}
