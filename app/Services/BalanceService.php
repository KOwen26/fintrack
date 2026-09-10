<?php

namespace App\Services;

use App\Enums\TransactionType;
use App\Models\Account;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class BalanceService
{
    public function forAccount(Account $account): string
    {
        $cacheKey = "balance:account:{$account->id}";

        return Cache::tags(["account:{$account->id}"])->rememberForever($cacheKey, function () use ($account): string {
            $inflowTypes = TransactionType::inflows();
            $outflowTypes = TransactionType::outflows();

            $inflowPlaceholders = implode(', ', array_fill(0, count($inflowTypes), '?'));
            $outflowPlaceholders = implode(', ', array_fill(0, count($outflowTypes), '?'));

            $balance = DB::table('accounts')
                ->selectRaw(
                    "accounts.initial_balance + COALESCE(SUM(CASE
                        WHEN t.type IN ({$inflowPlaceholders}) THEN t.amount
                        WHEN t.type IN ({$outflowPlaceholders}) THEN -t.amount
                        ELSE 0
                    END), 0) AS balance",
                    [...$inflowTypes, ...$outflowTypes]
                )
                ->leftJoin('transactions as t', function ($join): void {
                    $join->on('t.account_id', '=', 'accounts.id')->whereNull('t.deleted_at');
                })
                ->where('accounts.id', $account->id)
                ->groupBy('accounts.id', 'accounts.initial_balance')
                ->value('balance');

            return (string) ($balance ?? $account->initial_balance);
        });
    }
}
