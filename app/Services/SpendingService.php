<?php

namespace App\Services;

use App\Data\Report\CategorySpendingItemData;
use App\Data\Report\CategorySpendingReportData;
use App\Data\Report\ChildSpendingItemData;
use App\Data\Report\MonthlyFlowReportData;
use App\Data\Report\ParentSpendingItemData;
use App\Enums\CategoryGroup;
use App\Enums\DatePeriodPreset;
use App\Enums\TransactionType;
use App\Models\Transaction;
use App\Models\User;
use App\Objects\DailyFlow;
use Illuminate\Support\Facades\Date;

final class SpendingService
{
    /**
     * Aggregate category spending across multiple accounts for a given period.
     * SQL sums per category; the enum supplies presentation metadata and the
     * group rollup folds in PHP.
     */
    public function globalCategorySpending(array $accountIds, DatePeriodPreset $periodPreset): CategorySpendingReportData
    {
        $period = $periodPreset->toPeriod();

        if ($accountIds === []) {
            return CategorySpendingReportData::emptyForPeriod($period);
        }

        [$from, $to] = $period->toRange();

        $periodTotal = (float) Transaction::query()
            ->whereIn('account_id', $accountIds)
            ->where('type', TransactionType::Expense->value)
            ->whereBetween('transaction_date', [$from, $to])
            ->sum('amount');

        if ($periodTotal <= 0) {
            return CategorySpendingReportData::emptyForPeriod($period);
        }

        $rows = Transaction::query()
            ->whereIn('account_id', $accountIds)
            ->where('type', TransactionType::Expense->value)
            ->whereBetween('transaction_date', [$from, $to])
            ->selectRaw('category_id, SUM(amount) AS total, ROUND(SUM(amount) / ? * 100, 2) AS percentage', [$periodTotal])
            ->groupBy('category_id')
            ->orderByDesc('total')
            ->get();

        // Rows are hydrated Transaction models — category_id is already the
        // enum via the model cast; no raw-value resolution needed.
        $items = $rows
            ->filter(fn (Transaction $r): bool => $r->category_id !== null)
            ->map(fn (Transaction $r): CategorySpendingItemData => CategorySpendingItemData::fromEnum(
                $r->category_id,
                total: (float) $r->total,
                percentage: (float) $r->percentage,
            ))
            ->all();

        $groups = $this->groupByCategoryGroup($items, $periodTotal);

        return new CategorySpendingReportData(
            categories: $groups,
            period_total: $periodTotal,
            from: $from,
            to: $to,
        );
    }

    /**
     * Aggregate per-day income and expense across all of a user's accounts
     * for a period. SQL groups by day and type; the pivot into one
     * DailyFlow value object per activity day happens in PHP, with the net
     * derived inside the object. Days without activity are omitted — the
     * frontend calendar grid synthesizes them.
     */
    public function monthlyFlow(User $user, DatePeriodPreset $periodPreset): MonthlyFlowReportData
    {
        $period = $periodPreset->toPeriod();

        $accountIds = AccountService::getAccountsByUser($user)->pluck('id')->all();

        if ($accountIds === []) {
            return MonthlyFlowReportData::emptyForPeriod($period);
        }

        $rows = Transaction::query()
            ->whereIn('account_id', $accountIds)
            ->whereIn('type', [TransactionType::Income->value, TransactionType::Expense->value])
            ->withinPeriod($period)
            ->selectRaw('DATE(transaction_date) AS day, type, SUM(amount) AS total')
            ->groupBy('day', 'type')
            ->orderBy('day')
            ->get();

        /** @var array<string, array{income: float, expense: float}> $buckets */
        $buckets = [];

        foreach ($rows as $row) {
            $buckets[$row->day] ??= ['income' => 0.0, 'expense' => 0.0];
            $buckets[$row->day][$row->type->value] += (float) $row->total;
        }

        $flows = [];
        $totalIncome = 0.0;
        $totalExpense = 0.0;
        $maxAbsNet = 0.0;
        $maxIncome = 0.0;
        $maxExpense = 0.0;

        foreach ($buckets as $day => $bucket) {
            $flow = new DailyFlow(Date::parse($day), $bucket['income'], $bucket['expense']);

            $flows[] = $flow;
            $totalIncome += $flow->income;
            $totalExpense += $flow->expense;
            $maxAbsNet = max($maxAbsNet, abs($flow->net));
            $maxIncome = max($maxIncome, $flow->income);
            $maxExpense = max($maxExpense, $flow->expense);
        }

        return new MonthlyFlowReportData(
            data: $flows,
            from: $period->start,
            to: $period->end,
            total_income: $totalIncome,
            total_expense: $totalExpense,
            total_net: $totalIncome - $totalExpense,
            max_abs_net: $maxAbsNet,
            max_income: $maxIncome,
            max_expense: $maxExpense,
        );
    }

    /**
     * Roll flat category spending items up into their category groups.
     *
     * @param  CategorySpendingItemData[]  $items
     *
     * @return ParentSpendingItemData[]
     */
    public function groupByCategoryGroup(array $items, float $periodTotal): array
    {
        $grouped = [];

        foreach ($items as $item) {
            $grouped[$item->group][] = $item;
        }

        $result = [];

        foreach ($grouped as $groupValue => $children) {
            $group = CategoryGroup::from($groupValue);
            $groupTotal = array_sum(array_map(
                fn (CategorySpendingItemData $c): float => $c->total,
                $children,
            ));

            $result[] = new ParentSpendingItemData(
                group_id: $group->value,
                name: $group->label(),
                color: $group->decorations()->color,
                icon: $group->decorations()->icon,
                total: $groupTotal,
                percentage: $periodTotal > 0
                    ? round($groupTotal / $periodTotal * 100, 2)
                    : 0,
                children: $this->buildChildren($children, $groupTotal),
            );
        }

        usort($result, fn (ParentSpendingItemData $a, ParentSpendingItemData $b): int => $b->total <=> $a->total);

        return $result;
    }

    /**
     * Build ChildSpendingItemData array, recalculating percentages relative to the group subtotal.
     *
     * @param  CategorySpendingItemData[]  $children
     *
     * @return ChildSpendingItemData[]
     */
    private function buildChildren(array $children, float $groupSubtotal): array
    {
        return array_map(
            fn (CategorySpendingItemData $c): ChildSpendingItemData => new ChildSpendingItemData(
                category_id: $c->category_id,
                name: $c->name,
                color: $c->color,
                icon: $c->icon,
                total: $c->total,
                percentage: $groupSubtotal > 0
                    ? round($c->total / $groupSubtotal * 100, 2)
                    : 0,
            ),
            $children,
        );
    }
}
