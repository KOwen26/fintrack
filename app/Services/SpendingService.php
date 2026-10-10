<?php

namespace App\Services;

use App\Data\Report\CategorySpendingItemData;
use App\Data\Report\CategorySpendingReportData;
use App\Data\Report\ChildSpendingItemData;
use App\Data\Report\ParentSpendingItemData;
use App\Enums\CategoryGroup;
use App\Enums\DatePeriodPreset;
use App\Enums\TransactionType;
use App\Models\Transaction;

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

        $from = $period->startDate();
        $to = $period->endDate();

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
