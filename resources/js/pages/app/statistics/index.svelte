<script lang="ts">
    import type {
        BalanceTrendReportData,
        CashflowTrendReportData,
        MonthlyCashflowReportData,
        WeekdayPatternReportData,
    } from '@type/generated';

    import MobilePageLayout from '@components/layouts/mobile-page-layout.svelte';
    import BalanceTrendChart from '@components/module/statistics/balance-trend-chart.svelte';
    import CashflowTrendChart from '@components/module/statistics/cashflow-trend-chart.svelte';
    import MonthlyCalendarChart from '@components/module/statistics/monthly-calendar-chart.svelte';
    import WeekdayPatternChart from '@components/module/statistics/weekday-pattern-chart.svelte';
    import ResponsiveCard from '@components/ui/cards/responsive-card.svelte';

    // All report props are deferred — undefined while they stream in.
    let {
        monthlyCashflow,
        cashflowTrend,
        balanceTrend,
        weekdayPattern,
    }: {
        monthlyCashflow?: MonthlyCashflowReportData;
        cashflowTrend?: CashflowTrendReportData;
        balanceTrend?: BalanceTrendReportData;
        weekdayPattern?: WeekdayPatternReportData;
    } = $props();
</script>

<MobilePageLayout variant="3/5">
    {#snippet hero()}
        <WeekdayPatternChart pattern={weekdayPattern} />
    {/snippet}

    <div class="grid grid-cols-1 gap-6">
        <ResponsiveCard title="Monthly Cashflow">
            <MonthlyCalendarChart {monthlyCashflow} />
        </ResponsiveCard>

        <ResponsiveCard title="Income vs Expense">
            <CashflowTrendChart trend={cashflowTrend} />
        </ResponsiveCard>

        <ResponsiveCard title="Balance Trend">
            <BalanceTrendChart trend={balanceTrend} />
        </ResponsiveCard>
    </div>
</MobilePageLayout>
