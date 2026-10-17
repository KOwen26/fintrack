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
use App\Enums\StatisticDataTier;
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
    /** The fallback balance trend span when the preset has no rolling month count. */
    private const int DefaultBalanceSpanMonths = 6;

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
     * Aggregate per-month income and expense across the preset's window,
     * zero-filling months without activity (the final month may be partial).
     * SQL groups by month and type exactly like monthlyCashflow does by day;
     * months without activity are zero-filled server-side so the frontend
     * renders a continuous axis, with net and surplus rate derived inside
     * the MonthlyCashflow value object.
     */
    public function cashflowTrend(User $user, DatePeriodPreset $preset): CashflowTrendReportData
    {
        $accountIds = AccountService::getAccountIdsByUser($user)->all();

        $period = $this->trendPeriod($preset, $accountIds);

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

        // Walk every calendar month the window touches — a day-anchored
        // preset (e.g. Last 30 Days) may span two partial months, and
        // skipping the last one would drop its rows from the totals.
        $month = $period->start->startOfMonth();
        $endMonth = $period->end->startOfMonth();

        for (; $month->lessThanOrEqualTo($endMonth); $month = $month->addMonthsNoOverflow(1)) {
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
     * The window the trend chart reports on for a preset. Month-count
     * presets keep the classic whole-month rolling span so the chart always
     * ends with the current (partial) month; All Time is unbounded and gets
     * bounded at the accounts' earliest transaction month so the bucket
     * walk stays finite; every other preset uses its literal window.
     *
     * @param  list<int>  $accountIds
     */
    private function trendPeriod(DatePeriodPreset $preset, array $accountIds): DatePeriod
    {
        $months = $preset->rollingMonths();

        if ($months !== null) {
            return DatePeriod::forRollingMonths($months);
        }

        if ($preset === DatePeriodPreset::All) {
            $earliest = $accountIds === []
                ? null
                : Transaction::query()->whereIn('account_id', $accountIds)->min('transaction_date');

            // No history at all — report the current month as a single
            // zero-filled bucket instead of a 26-year axis of zeros.
            return $earliest === null
                ? DatePeriod::forMonth(now())
                : new DatePeriod(Date::parse($earliest)->startOfMonth());
        }

        return $preset->toPeriod();
    }

    /**
     * Reconstruct month-end total balance over a rolling span. Monthly deltas
     * aggregate every transaction type — transfers self-cancel while both
     * legs sit inside the account scope, and fees are ordinary expense rows —
     * then the series is walked backward from the accounts' current balance
     * total, so historical row drift can never accumulate and the series
     * lands exactly on the dashboard's number. Rolling month presets supply
     * the span; anything else falls back to the default 6-month window.
     */
    public function balanceTrend(User $user, DatePeriodPreset $preset): BalanceTrendReportData
    {
        $period = DatePeriod::forRollingMonths($preset->rollingMonths() ?? self::DefaultBalanceSpanMonths);

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
     * Compare each weekday's spending this week against its trailing
     * three-month average. Two grouped queries (MySQL WEEKDAY(), Monday = 0)
     * — the current week and the baseline window — then per-weekday
     * baselines divide the window totals by how often each weekday occurs
     * in it, so a 4-vs-5-occurrence month compares fairly. All seven rows
     * are always emitted, zero-filled; days after today carry current = 0
     * (week-to-date).
     */
    public function weekdayPattern(User $user): WeekdayPatternReportData
    {
        $week = DatePeriod::forWeek();
        $baselinePeriod = DatePeriod::forRollingMonths(3);

        $accountIds = AccountService::getAccountIdsByUser($user)->all();

        /** @var array<int, float> $currentByWeekday keyed ISO weekday */
        $currentByWeekday = array_fill(1, 7, 0.0);

        if ($accountIds !== []) {
            $currentRows = Transaction::query()
                ->whereIn('account_id', $accountIds)
                ->where('type', TransactionType::Expense->value)
                ->withinPeriod($week)
                ->selectRaw('WEEKDAY(transaction_date) AS weekday, SUM(amount) AS total')
                ->groupBy('weekday')
                ->get();

            foreach ($currentRows as $row) {
                $currentByWeekday[(int) $row->weekday + 1] = (float) $row->total;
            }
        }

        /** @var array<int, float> $baselineTotals keyed ISO weekday */
        $baselineTotals = array_fill(1, 7, 0.0);

        if ($accountIds !== []) {
            $baselineRows = Transaction::query()
                ->whereIn('account_id', $accountIds)
                ->where('type', TransactionType::Expense->value)
                ->withinPeriod($baselinePeriod)
                ->selectRaw('WEEKDAY(transaction_date) AS weekday, SUM(amount) AS total')
                ->groupBy('weekday')
                ->get();

            foreach ($baselineRows as $row) {
                $baselineTotals[(int) $row->weekday + 1] = (float) $row->total;
            }
        }

        // The averaging base — how often each weekday occurs in the window.
        $occurrences = array_fill(1, 7, 0);

        for ($day = $baselinePeriod->start; $day->lessThan($baselinePeriod->end); $day = $day->addDay()) {
            $occurrences[(int) $day->format('N')]++;
        }

        // Emphasis tiers — the two heaviest spending days of the scope step
        // up the tint ladder; zero-current days (week not yet reached them)
        // always stay muted, whatever their rank among empty rows. Ties keep
        // the earlier weekday (uasort is stable over the Mon→Sun insertion).
        $tiers = array_fill(1, 7, StatisticDataTier::Rest);

        $eligible = array_filter(
            $currentByWeekday,
            fn (float $current): bool => $current > 0,
        );

        uasort($eligible, fn (float $a, float $b): int => $b <=> $a);

        foreach (array_keys($eligible) as $index => $weekday) {
            $tiers[$weekday] = $index === 0
                ? StatisticDataTier::Top
                : ($index === 1 ? StatisticDataTier::Second : StatisticDataTier::Rest);
        }

        $data = [];
        $totalExpense = 0.0;

        for ($weekday = 1; $weekday <= 7; $weekday++) {
            $baseline = round($baselineTotals[$weekday] / max($occurrences[$weekday], 1), 2);
            $current = $currentByWeekday[$weekday];

            $data[] = new WeekdayCashflow($weekday, $current, $baseline, $tiers[$weekday]);
            $totalExpense += $current;
        }

        // Strict `>` scans over the Mon→Sun-ordered rows keep the earliest
        // weekday on ties. The deviation peak only counts days that are
        // actually above their baseline — "under average" is not a peak.
        $peakWeekday = null;
        $peakDeviationWeekday = null;
        $maxCurrent = 0.0;
        $maxDeviation = 0.0;

        foreach ($data as $cashflow) {
            if ($cashflow->current > $maxCurrent) {
                $maxCurrent = $cashflow->current;
                $peakWeekday = $cashflow->weekday;
            }

            if ($cashflow->current > 0 && $cashflow->deviation > $maxDeviation) {
                $maxDeviation = $cashflow->deviation;
                $peakDeviationWeekday = $cashflow->weekday;
            }
        }

        return new WeekdayPatternReportData(
            data: $data,
            from: $week->start,
            to: $week->end,
            baseline_from: $baselinePeriod->start,
            baseline_to: $baselinePeriod->end,
            total_expense: $totalExpense,
            peak_weekday: $peakWeekday,
            peak_deviation_weekday: $peakDeviationWeekday,
        );
    }

    /**
     * Current-week expense projected to a full week — week-to-date ÷ days
     * elapsed × 7. The week is Monday-based, matching the weekday pattern
     * beside it, so the hero number reads as "at this pace, X per week".
     */
    public function weeklySpendingAverage(User $user): float
    {
        $week = DatePeriod::forWeek();

        $accountIds = AccountService::getAccountIdsByUser($user);

        if ($accountIds->isEmpty()) {
            return 0.0;
        }

        $amountAverage = (float) Transaction::query()
            ->whereIn('account_id', $accountIds)
            ->where('type', TransactionType::Expense)
            ->withinPeriod($week)
            ->avg('amount');

        return $amountAverage;
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
