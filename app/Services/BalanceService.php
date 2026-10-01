<?php

namespace App\Services;

use App\Models\Account;
use Illuminate\Support\Facades\DB;

class BalanceService
{
    public function forAccount(Account $account): string
    {
        $balance = DB::table('accounts')
            ->selectRaw(
                "accounts.initial_balance + COALESCE(SUM(CASE
                    WHEN t.flow = 'inflow' THEN t.amount
                    ELSE -t.amount
                END), 0) AS balance"
            )
            ->leftJoin('transactions as t', function ($join): void {
                $join->on('t.account_id', '=', 'accounts.id')->whereNull('t.deleted_at');
            })
            ->where('accounts.id', $account->id)
            ->groupBy('accounts.id', 'accounts.initial_balance')
            ->value('balance');

        return (string) ($balance ?? $account->initial_balance);
    }
}
