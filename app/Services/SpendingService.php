<?php

namespace App\Services;

use App\Data\Report\BalanceTrendReportData;
use App\Data\Report\CashflowTrendReportData;
use App\Data\Report\CategorySpendingItemData;
use App\Data\Report\CategorySpendingReportData;
use App\Data\Report\ChildSpendingItemData;
use App\Data\Report\MonthlyCashflowReportData;
use App\Data\Report\ParentSpendingItemData;
use App\Data\Report\WeekdayPatternReportData;
use App\Enums\Cashflow;
use App\Enums\CategoryGroup;
use App\Enums\DatePeriodPreset;
use App\Enums\TransactionType;
use App\Models\Transaction;
use App\Models\User;
use App\Objects\DailyCashflow;
use App\Objects\DatePeriod;
use App\Objects\MonthlyCashflow;
use App\Objects\WeekdayCashflow;
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
     * for a period — a preset or an explicit window (e.g. a navigated month).
     * SQL groups by day and type; the pivot into one DailyFlow value object
     * per activity day happens in PHP, with the net derived inside the object.
     * Days without activity are omitted — the frontend calendar grid
     * synthesizes them.
     */
    public function monthlyCashflow(User $user, DatePeriodPreset | DatePeriod $period): MonthlyCashflowReportData
    {
        $period = $period instanceof DatePeriodPreset ? $period->toPeriod() : $period;

        $accountIds = AccountService::getAccountIdsByUser($user)->all();

        if ($accountIds === []) {
            return MonthlyCashflowReportData::emptyForPeriod($period);
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

        $data = [];
        $totalIncome = 0.0;
        $totalExpense = 0.0;
        $maxAbsNet = 0.0;
        $maxIncome = 0.0;
        $maxExpense = 0.0;

        foreach ($buckets as $day => $bucket) {
            $cashflow = new DailyCashflow(Date::parse($day), $bucket['income'], $bucket['expense']);

            $data[] = $cashflow;
            $totalIncome += $cashflow->income;
            $totalExpense += $cashflow->expense;
            $maxAbsNet = max($maxAbsNet, abs($cashflow->net));
            $maxIncome = max($maxIncome, $cashflow->income);
            $maxExpense = max($maxExpense, $cashflow->expense);
        }

        return new MonthlyCashflowReportData(
            data: $data,
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
     * Aggregate per-month income and expense over a rolling span of whole
     * months ending with the current one (the final month may be partial).
     * SQL groups by month and type exactly like monthlyCashflow does by day;
     * months without activity are zero-filled server-side so the frontend
     * renders a continuous axis, with net and surplus rate derived inside
     * the MonthlyCashflow value object.
     */
    public function cashflowTrend(User $user, int $months): CashflowTrendReportData
    {
        $period = DatePeriod::forRollingMonths($months);

        $accountIds = AccountService::getAccountIdsByUser($user)->all();

        $rows = $accountIds === [] ? [] : Transaction::query()
            ->whereIn('account_id', $accountIds)
            ->whereIn('type', [TransactionType::Income->value, TransactionType::Expense->value])
            ->withinPeriod($period)
            ->selectRaw("DATE_FORMAT(transaction_date, '%Y-%m') AS month, type, SUM(amount) AS total")
            ->groupBy('month', 'type')
            ->orderBy('month')
            ->get();

        /** @var array<string, array{income: float, expense: float}> $buckets */
        $buckets = [];

        foreach ($rows as $row) {
            $buckets[$row->month] ??= ['income' => 0.0, 'expense' => 0.0];
            $buckets[$row->month][$row->type->value] += (float) $row->total;
        }

        $data = [];
        $totalIncome = 0.0;
        $totalExpense = 0.0;

        for ($month = $period->start; $month->lessThan($period->end); $month = $month->addMonthsNoOverflow(1)) {
            $bucket = $buckets[$month->format('Y-m')] ?? ['income' => 0.0, 'expense' => 0.0];

            $cashflow = new MonthlyCashflow($month, $bucket['income'], $bucket['expense']);

            $data[] = $cashflow;
            $totalIncome += $cashflow->income;
            $totalExpense += $cashflow->expense;
        }

        return new CashflowTrendReportData(
            data: $data,
            from: $period->start,
            to: $period->end,
            total_income: $totalIncome,
            total_expense: $totalExpense,
            total_net: $totalIncome - $totalExpense,
        );
    }

    /**
     * Reconstruct month-end total balance over a rolling span. Monthly deltas
     * aggregate every transaction type — transfers self-cancel while both
     * legs sit inside the account scope, and fees are ordinary expense rows —
     * then the series is walked backward from the accounts' current balance
     * total, so historical row drift can never accumulate and the series
     * lands exactly on the dashboard's number.
     */
    public function balanceTrend(User $user, int $months): BalanceTrendReportData
    {
        $period = DatePeriod::forRollingMonths($months);

        $accounts = AccountService::getAccountsByUser($user);
        $accountIds = $accounts->pluck('id')->all();

        $rows = $accountIds === [] ? [] : Transaction::query()
            ->whereIn('account_id', $accountIds)
            ->withinPeriod($period)
            ->selectRaw("DATE_FORMAT(transaction_date, '%Y-%m') AS month, SUM(CASE WHEN flow = ? THEN amount ELSE -amount END) AS delta", [Cashflow::Inflow->value])
            ->groupBy('month')
            ->orderByDesc('month')
            ->get();

        /** @var array<string, float> $deltas */
        $deltas = [];

        foreach ($rows as $row) {
            $deltas[$row->month] = (float) $row->delta;
        }

        // Newest first: the latest month-end equals the current total, then
        // each older month-end subtracts the month that follows it.
        $data = [];
        $balance = (float) $accounts->sum('current_balance');

        for ($month = $period->end->startOfMonth(); $month->greaterThanOrEqualTo($period->start); $month = $month->subMonthsNoOverflow(1)) {
            $delta = $deltas[$month->format('Y-m')] ?? 0.0;

            $data[] = [
                'date' => $month->endOfMonth()->toDateString(),
                'balance' => $balance,
                'net_delta' => $delta,
            ];

            $balance -= $delta;
        }

        return new BalanceTrendReportData(
            data: array_reverse($data),
            from: $period->start,
            to: $period->end,
            current_total: (float) $accounts->sum('current_balance'),
        );
    }

    /**
     * Aggregate expense by day-of-week over a fixed trailing six-month
     * window. SQL groups by MySQL WEEKDAY() (Monday = 0); the averaging base
     * is how often each weekday occurs in the window, so bars compare fairly
     * across months that hold four or five of each. All seven rows are
     * always emitted, zero-filled, with averages, shares, and the peak
     * (highest-average) weekday resolved here.
     */
    public function weekdayPattern(User $user): WeekdayPatternReportData
    {
        $period = DatePeriod::forRollingMonths(6);

        $accountIds = AccountService::getAccountIdsByUser($user)->all();

        $rows = $accountIds === [] ? [] : Transaction::query()
            ->whereIn('account_id', $accountIds)
            ->where('type', TransactionType::Expense->value)
            ->withinPeriod($period)
            ->selectRaw('WEEKDAY(transaction_date) AS weekday, SUM(amount) AS total, COUNT(*) AS transactions')
            ->groupBy('weekday')
            ->get();

        /** @var array<int, array{total: float, transactions: int}> $buckets keyed ISO weekday */
        $buckets = [];

        foreach ($rows as $row) {
            $buckets[((int) $row->weekday) + 1] = [
                'total' => (float) $row->total,
                'transactions' => (int) $row->transactions,
            ];
        }

        $occurrences = array_fill(1, 7, 0);

        for ($day = $period->start; $day->lessThan($period->end); $day = $day->addDay()) {
            $occurrences[(int) $day->format('N')]++;
        }

        $totalExpense = array_sum(array_column($buckets, 'total'));

        $data = [];

        for ($weekday = 1; $weekday <= 7; $weekday++) {
            $bucket = $buckets[$weekday] ?? ['total' => 0.0, 'transactions' => 0];

            $data[] = new WeekdayCashflow(
                weekday: $weekday,
                expense: $bucket['total'],
                count: $bucket['transactions'],
                occurrences: $occurrences[$weekday],
                windowTotal: $totalExpense,
            );
        }

        // Strict `>` keeps the earliest weekday on ties (data runs Mon→Sun).
        $peakWeekday = null;
        $maxAverage = 0.0;

        foreach ($data as $cashflow) {
            if ($cashflow->average > $maxAverage) {
                $maxAverage = $cashflow->average;
                $peakWeekday = $cashflow->weekday;
            }
        }

        return new WeekdayPatternReportData(
            data: $data,
            from: $period->start,
            to: $period->end,
            total_expense: $totalExpense,
            peak_weekday: $peakWeekday,
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
