<?php

namespace App\Services;

use App\Data\Report\CategorySpendingItemData;
use App\Data\Report\CategorySpendingReportData;
use App\Data\Report\ContributionMemberData;
use App\Data\Report\ContributionSplitData;
use App\Data\Report\FixedVariableData;
use App\Data\Report\TrendMonthData;
use App\Data\Report\TrendReportData;
use App\Enums\AccountAccessType;
use App\Enums\TransactionType;
use App\Models\Account;
use Carbon\Carbon;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;

class ReportService
{
    /**
     * Income vs Expense Trend — last N months of monthly income/expense/net rows.
     */
    public function trend(Account $account, int $months = 6): TrendReportData
    {
        $from = Date::now()->startOfMonth()->subMonths($months - 1);

        $rows = [];
        $cursor = $from->copy();

        while ($cursor->lte(Date::now()->startOfMonth())) {
            $year = $cursor->year;
            $month = $cursor->month;

            $result = DB::table('transactions')
                ->selectRaw('
                    SUM(CASE WHEN type = ? THEN amount ELSE 0 END) AS total_income,
                    SUM(CASE WHEN type = ? THEN amount ELSE 0 END) AS total_expense
                ', [TransactionType::Income->value, TransactionType::Expense->value])
                ->where('account_id', $account->id)
                ->whereNull('deleted_at')
                ->whereYear('transaction_date', $year)
                ->whereMonth('transaction_date', $month)
                ->first();

            $income = (float) ($result->total_income ?? 0);
            $expense = (float) ($result->total_expense ?? 0);
            $net = $income - $expense;
            $surplusRate = $income > 0
                ? round(($net / $income) * 100, 2)
                : 0.0;

            $rows[] = new TrendMonthData(
                year: $year,
                month: $month,
                income: $income,
                expense: $expense,
                net: $net,
                surplus_rate: $surplusRate,
            );

            $cursor->addMonth();
        }

        return new TrendReportData(
            months: $rows,
            months_count: count($rows),
        );
    }

    /**
     * Category Leak — expense totals ranked by category for a given period.
     */
    public function categorySpending(Account $account, Carbon $from, Carbon $to): CategorySpendingReportData
    {
        // Compute the period total first (used to calculate percentages inside the DB)
        $periodTotal = (float) DB::table('transactions')
            ->where('account_id', $account->id)
            ->where('type', TransactionType::Expense->value)
            ->whereBetween('transaction_date', [$from->toDateString(), $to->toDateString()])
            ->whereNull('deleted_at')
            ->sum('amount');

        if ($periodTotal <= 0) {
            return new CategorySpendingReportData(
                categories: [],
                period_total: 0.0,
                from: $from->toDateString(),
                to: $to->toDateString(),
            );
        }

        $rows = DB::table('transactions as t')
            ->join('categories as c', 'c.id', '=', 't.category_id')
            ->selectRaw("
                c.name,
                JSON_UNQUOTE(JSON_EXTRACT(c.decorations, '$.color')) AS color,
                JSON_UNQUOTE(JSON_EXTRACT(c.decorations, '$.icon')) AS icon,
                SUM(t.amount) AS total,
                ROUND(SUM(t.amount) / ? * 100, 2) AS percentage
            ", [$periodTotal])
            ->where('t.account_id', $account->id)
            ->where('t.type', TransactionType::Expense->value)
            ->whereBetween('t.transaction_date', [$from->toDateString(), $to->toDateString()])
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
            from: $from->toDateString(),
            to: $to->toDateString(),
        );
    }

    /**
     * Joint Contribution Split — income share by household member.
     * Returns is_joint: false with empty members for personal accounts (not an error).
     */
    public function contributionSplit(Account $account, Carbon $from, Carbon $to): ContributionSplitData
    {
        if ($account->access_type !== AccountAccessType::Joint) {
            return new ContributionSplitData(
                is_joint: false,
                members: [],
                total: 0.0,
                from: $from->toDateString(),
                to: $to->toDateString(),
            );
        }

        $total = (float) DB::table('transactions')
            ->where('account_id', $account->id)
            ->where('type', 'income')
            ->whereBetween('transaction_date', [$from->toDateString(), $to->toDateString()])
            ->whereNull('deleted_at')
            ->sum('amount');

        if ($total <= 0) {
            return new ContributionSplitData(
                is_joint: true,
                members: [],
                total: 0.0,
                from: $from->toDateString(),
                to: $to->toDateString(),
            );
        }

        $rows = DB::table('transactions as t')
            ->join('users as u', 'u.id', '=', 't.created_by')
            ->selectRaw('u.name, SUM(t.amount) AS contributed')
            ->where('t.account_id', $account->id)
            ->where('t.type', 'income')
            ->whereBetween('t.transaction_date', [$from->toDateString(), $to->toDateString()])
            ->whereNull('t.deleted_at')
            ->groupBy('t.created_by', 'u.name')
            ->get();

        $members = $rows->map(fn (object $r): ContributionMemberData => new ContributionMemberData(
            name: $r->name,
            contributed: (float) $r->contributed,
            percentage: round((float) $r->contributed / $total * 100, 2),
        ))->all();

        return new ContributionSplitData(
            is_joint: true,
            members: $members,
            total: $total,
            from: $from->toDateString(),
            to: $to->toDateString(),
        );
    }

    /**
     * Fixed vs Variable — compares fixed-cost category spending vs variable for a period.
     */
    public function fixedVsVariable(Account $account, Carbon $from, Carbon $to): FixedVariableData
    {
        $rows = DB::table('transactions as t')
            ->join('categories as c', 'c.id', '=', 't.category_id')
            ->selectRaw('c.is_fixed_cost, SUM(t.amount) AS total')
            ->where('t.account_id', $account->id)
            ->where('t.type', TransactionType::Expense->value)
            ->whereBetween('t.transaction_date', [$from->toDateString(), $to->toDateString()])
            ->whereNull('t.deleted_at')
            ->groupBy('c.is_fixed_cost')
            ->get()
            ->keyBy('is_fixed_cost');

        $fixedTotal = (float) ($rows->get(1)?->total ?? $rows->get(true)?->total ?? 0);
        $variableTotal = (float) ($rows->get(0)?->total ?? $rows->get(false)?->total ?? 0);
        $grandTotal = $fixedTotal + $variableTotal;

        $fixedPct = $grandTotal > 0 ? round($fixedTotal / $grandTotal * 100, 2) : 0.0;
        $variablePct = $grandTotal > 0 ? round($variableTotal / $grandTotal * 100, 2) : 0.0;

        // Safety margin: percentage of total spend that is non-discretionary (fixed).
        // A lower fixed% means more flexibility — so safety margin = variable %.
        $safetyMargin = $variablePct;

        return new FixedVariableData(
            fixed_total: $fixedTotal,
            variable_total: $variableTotal,
            fixed_pct: $fixedPct,
            variable_pct: $variablePct,
            safety_margin: $safetyMargin,
            from: $from->toDateString(),
            to: $to->toDateString(),
        );
    }
}
