<?php

namespace App\Services;

use App\Models\Account;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class BalanceService
{
    public function forAccount(Account $account): string
    {
        $cacheKey = "balance:account:{$account->id}";

        return Cache::tags(["account:{$account->id}"])->rememberForever($cacheKey, function () use ($account): string {
            $balance = DB::table('accounts')
                ->selectRaw(
                    'accounts.initial_balance + COALESCE(SUM(CASE
                        WHEN t.type IN (?, ?) THEN t.amount
                        WHEN t.type IN (?, ?, ?) THEN -t.amount
                        ELSE 0
                    END), 0) AS balance',
                    ['income', 'transfer_in', 'expense', 'transfer_out', 'fee']
                )
                ->leftJoin('transactions as t', function ($join): void {
                    $join->on('t.account_id', '=', 'accounts.id')
                        ->whereNull('t.deleted_at');
                })
                ->where('accounts.id', $account->id)
                ->groupBy('accounts.id', 'accounts.initial_balance')
                ->value('balance');

            return (string) ($balance ?? $account->initial_balance);
        });
    }
}
