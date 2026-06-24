<?php

namespace App\Services;

use App\Data\Report\CategorySpendingItemData;
use App\Data\Report\CategorySpendingReportData;
use App\Data\Report\ChildSpendingItemData;
use App\Data\Report\ParentSpendingItemData;
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
            ->leftJoin('categories as parent', 'c.parent_id', '=', 'parent.id')
            ->selectRaw("
                c.id AS category_id,
                c.name,
                c.decorations->>'$.color.value' AS color,
                c.decorations->>'$.icon.value' AS icon,
                c.parent_id,
                parent.name AS parent_name,
                SUM(t.amount) AS total,
                ROUND(SUM(t.amount) / ? * 100, 2) AS percentage
            ", [$periodTotal])
            ->whereIn('t.account_id', $accountIds)
            ->whereIn('t.type', ['expense', 'fee'])
            ->whereBetween('t.transaction_date', [$from, $to])
            ->whereNull('t.deleted_at')
            ->groupBy('t.category_id', 'c.id', 'c.name', 'color', 'icon', 'c.parent_id', 'parent.name')
            ->orderByDesc('total')
            ->get();

        $items = $rows->map(fn (object $r): CategorySpendingItemData => new CategorySpendingItemData(
            name: $r->name,
            color: $r->color,
            icon: $r->icon,
            total: (float) $r->total,
            percentage: (float) $r->percentage,
            categoryId: (int) $r->category_id,
            parentId: $r->parent_id ? (int) $r->parent_id : null,
            parentName: $r->parent_name ?? null,
        ))->all();

        $parentGroups = $this->groupByParent($items, $periodTotal);

        return new CategorySpendingReportData(
            categories: $parentGroups,
            period_total: $periodTotal,
            from: $from,
            to: $to,
        );
    }

    /**
     * Transform flat category spending items into parent-grouped structure.
     *
     * @param  CategorySpendingItemData[]  $items
     *
     * @return ParentSpendingItemData[]
     */
    public function groupByParent(array $items, float $periodTotal): array
    {
        // Separate into parent rows (top-level categories) and child rows
        $parentRows = [];
        $childRows = [];

        foreach ($items as $item) {
            if ($item->parentId === null) {
                $parentRows[] = $item;
            } else {
                $childRows[] = $item;
            }
        }

        // Index parent rows by their own category ID
        $parentMap = [];
        foreach ($parentRows as $parent) {
            $parentMap[$parent->categoryId] = $parent;
        }

        // Group child rows by their parent_id
        $childMap = [];
        foreach ($childRows as $child) {
            $childMap[$child->parentId][] = $child;
        }

        $result = [];

        // Case 1: Parents that have direct spending (anchor row exists)
        foreach ($parentMap as $parentId => $parent) {
            $children = $childMap[$parentId] ?? [];

            $childrenTotal = array_sum(array_map(fn (CategorySpendingItemData $c): float => $c->total, $children));
            $groupTotal = $parent->total + $childrenTotal;
            $groupPercentage = $periodTotal > 0
                ? round($groupTotal / $periodTotal * 100, 2)
                : 0;

            $childItems = $this->buildChildren($children, $groupTotal);

            $result[] = new ParentSpendingItemData(
                categoryId: $parentId,
                name: $parent->name,
                color: $parent->color,
                icon: $parent->icon,
                total: $groupTotal,
                percentage: $groupPercentage,
                children: $childItems,
            );
        }

        // Case 2: Children whose parent has no direct spending (synthesized parent)
        foreach ($childMap as $parentId => $children) {
            if (isset($parentMap[$parentId])) {
                continue; // Already handled above
            }

            $firstChild = $children[0];
            $childrenTotal = array_sum(array_map(fn (CategorySpendingItemData $c): float => $c->total, $children));
            $groupPercentage = $periodTotal > 0
                ? round($childrenTotal / $periodTotal * 100, 2)
                : 0;

            $childItems = $this->buildChildren($children, $childrenTotal);

            $result[] = new ParentSpendingItemData(
                categoryId: $parentId,
                name: $firstChild->parentName ?? $firstChild->name,
                color: $firstChild->color,
                icon: $firstChild->icon,
                total: $childrenTotal,
                percentage: $groupPercentage,
                children: $childItems,
            );
        }

        // Sort by total descending
        usort($result, fn (ParentSpendingItemData $a, ParentSpendingItemData $b): int => $b->total <=> $a->total);

        return $result;
    }

    /**
     * Build ChildSpendingItemData array, recalculating percentages relative to parent subtotal.
     *
     * @param  CategorySpendingItemData[]  $children
     *
     * @return ChildSpendingItemData[]
     */
    private function buildChildren(array $children, float $parentSubtotal): array
    {
        return array_map(
            fn (CategorySpendingItemData $c): ChildSpendingItemData => new ChildSpendingItemData(
                categoryId: $c->categoryId,
                name: $c->name,
                color: $c->color,
                icon: $c->icon,
                total: $c->total,
                percentage: $parentSubtotal > 0
                    ? round($c->total / $parentSubtotal * 100, 2)
                    : 0,
            ),
            $children,
        );
    }
}
