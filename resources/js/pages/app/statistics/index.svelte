<script lang="ts">
    import type {
        BalanceTrendReportData,
        CashflowTrendReportData,
        CategorySpendingReportData,
        MonthlyCashflowReportData,
        SavingsRateReportData,
        WeekdayPatternReportData,
    } from '@type/generated';

    import { pageLayoutProps } from '@lib/navigation-helper.svelte';

    import CurrencyAmount from '@components/data/currency-amount.svelte';
    import MobilePageLayout from '@components/layouts/mobile-page-layout.svelte';
    import BalanceTrendChart from '@components/module/statistics/balance-trend-chart.svelte';
    import CashflowTrendChart from '@components/module/statistics/cashflow-trend-chart.svelte';
    import CategorySpendingChart from '@components/module/statistics/category-spending-chart.svelte';
    import MonthlyCalendarChart from '@components/module/statistics/monthly-calendar-chart.svelte';
    import SavingsRateChart from '@components/module/statistics/savings-rate-chart.svelte';
    import WeekdayPatternChart from '@components/module/statistics/weekday-pattern-chart.svelte';
    import ResponsiveCard from '@components/ui/cards/responsive-card.svelte';
    import Separator from '@components/ui/separator.svelte';
    import Skeleton from '@components/ui/skeleton.svelte';

    // All report props are deferred — undefined while they stream in.
    let {
        weeklySpendingAverage,
        monthlyCashflow,
        cashflowTrend,
        balanceTrend,
        savingsRate,
        weekdayPattern,
        categorySpending,
    }: {
        weeklySpendingAverage?: number;
        monthlyCashflow?: MonthlyCashflowReportData;
        cashflowTrend?: CashflowTrendReportData;
        balanceTrend?: BalanceTrendReportData;
        savingsRate?: SavingsRateReportData;
        weekdayPattern?: WeekdayPatternReportData;
        categorySpending?: CategorySpendingReportData;
    } = $props();

    pageLayoutProps({
        title: 'Statistics',
    });
</script>

<MobilePageLayout variant="3/5">
    {#snippet hero()}
        <div>
            <div class="mb-6">
                <h3 class="text-base tracking-wide text-accent-200">Average Weekly Spendings</h3>

                <CurrencyAmount
                    class="text-5xl font-semibold tracking-tighter text-accent"
                    value={weeklySpendingAverage?.toFixed(0)} />
            </div>

            <WeekdayPatternChart pattern={weekdayPattern} />
        </div>
    {/snippet}

    <div class="grid grid-cols-1 gap-6">
        <ResponsiveCard title="Monthly Cashflow">
            <MonthlyCalendarChart {monthlyCashflow} />
        </ResponsiveCard>

        <Separator class="-mx-5 w-auto!" variant="thick" />

        <ResponsiveCard title="Savings Rate">
            <SavingsRateChart report={savingsRate} />
        </ResponsiveCard>

        <Separator class="-mx-5 w-auto!" variant="thick" />

        <ResponsiveCard title="Income vs Expense">
            <CashflowTrendChart trend={cashflowTrend} />
        </ResponsiveCard>

        <Separator class="-mx-5 w-auto!" variant="thick" />

        <ResponsiveCard title="Balance Trend">
            <BalanceTrendChart trend={balanceTrend} />
        </ResponsiveCard>

        <Separator class="-mx-5 w-auto!" variant="thick" />

        <ResponsiveCard title="Spending By Category">
            {#if categorySpending}
                <CategorySpendingChart {categorySpending} />
            {:else}
                <!-- Deferred prop skeleton — the report streams in after first paint. -->
                <Skeleton class="aspect-square w-full" />
            {/if}
        </ResponsiveCard>

        <Separator class="-mx-5 w-auto!" variant="thick" />

        <div></div>
    </div>
</MobilePageLayout>
