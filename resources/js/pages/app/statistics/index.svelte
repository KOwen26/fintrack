<script lang="ts">
    import type {
        BalanceTrendReportData,
        CashflowTrendReportData,
        MonthlyCashflowReportData,
        WeekdayPatternReportData,
    } from '@type/generated';

    import { setLayoutProps } from '@inertiajs/svelte';

    import CurrencyAmount from '@components/data/currency-amount.svelte';
    import MobilePageLayout from '@components/layouts/mobile-page-layout.svelte';
    import BalanceTrendChart from '@components/module/statistics/balance-trend-chart.svelte';
    import CashflowTrendChart from '@components/module/statistics/cashflow-trend-chart.svelte';
    import MonthlyCalendarChart from '@components/module/statistics/monthly-calendar-chart.svelte';
    import WeekdayPatternChart from '@components/module/statistics/weekday-pattern-chart.svelte';
    import ResponsiveCard from '@components/ui/cards/responsive-card.svelte';

    // All report props are deferred — undefined while they stream in.
    let {
        weeklySpendingAverage,
        monthlyCashflow,
        cashflowTrend,
        balanceTrend,
        weekdayPattern,
    }: {
        weeklySpendingAverage?: number;
        monthlyCashflow?: MonthlyCashflowReportData;
        cashflowTrend?: CashflowTrendReportData;
        balanceTrend?: BalanceTrendReportData;
        weekdayPattern?: WeekdayPatternReportData;
    } = $props();

    setLayoutProps({
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

        {@render separator?.()}

        <ResponsiveCard title="Income vs Expense">
            <CashflowTrendChart trend={cashflowTrend} />
        </ResponsiveCard>

        {@render separator?.()}

        <ResponsiveCard title="Balance Trend">
            <BalanceTrendChart trend={balanceTrend} />
        </ResponsiveCard>

        {@render separator?.()}

        <div></div>
    </div>
</MobilePageLayout>

{#snippet separator()}
    <div class="-mx-5 h-2.5 bg-muted"></div>
{/snippet}
