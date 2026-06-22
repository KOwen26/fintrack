<?php

namespace App\Services;

use App\Data\Report\CategorySpendingItemData;
use App\Data\Report\CategorySpendingReportData;
use Illuminate\Support\Facades\DB;

class SpendingService
{
    /**
     * Aggregate category spending across multiple accounts for a given period.
     */
    public function globalCategorySpending(array $accountIds, string $from, string $to): CategorySpendingReportData
    {
        $periodTotal = (float) DB::table('transactions')
            ->whereIn('account_id', $accountIds)
            ->whereIn('type', ['expense', 'fee'])
            ->whereBetween('transaction_date', [$from, $to])
            ->whereNull('deleted_at')
            ->sum('amount');

        if ($periodTotal <= 0) {
            return new CategorySpendingReportData(
                categories: [],
                period_total: 0.0,
                from: $from,
                to: $to,
            );
        }

        $rows = DB::table('transactions as t')
            ->join('categories as c', 'c.id', '=', 't.category_id')
            ->selectRaw("
                c.name,
                c.decorations->>'$.color.value' AS color,
                c.decorations->>'$.icon.value' AS icon,
                SUM(t.amount) AS total,
                ROUND(SUM(t.amount) / ? * 100, 2) AS percentage
            ", [$periodTotal])
            ->whereIn('t.account_id', $accountIds)
            ->whereIn('t.type', ['expense', 'fee'])
            ->whereBetween('t.transaction_date', [$from, $to])
            ->whereNull('t.deleted_at')
            ->groupBy('t.category_id', 'c.name', 'color', 'icon')
            ->orderByDesc('total')
            ->get();

        $categories = $rows->map(fn (object $r): CategorySpendingItemData => new CategorySpendingItemData(
            name: $r->name,
            color: $r->color,
            icon: $r->icon,
            total: (float) $r->total,
            percentage: (float) $r->percentage,
        ))->all();

        return new CategorySpendingReportData(
            categories: $categories,
            period_total: $periodTotal,
            from: $from,
            to: $to,
        );
    }
}
