<script lang="ts">
    import type { MonthlyFlowReportData } from '@type/generated';

    import MobilePageLayout from '@components/layouts/mobile-page-layout.svelte';
    import MonthlyCalendarChart from '@components/module/statistics/monthly-calendar-chart.svelte';
    import ResponsiveCard from '@components/ui/cards/responsive-card.svelte';

    let { monthlyFlow }: { monthlyFlow: MonthlyFlowReportData } = $props();
</script>

<MobilePageLayout variant="3/5">
    {#snippet hero()}

    {/snippet}

    <div class="grid grid-cols-1 gap-6">
        <ResponsiveCard title="Monthly Cashflow">
            <MonthlyCalendarChart {monthlyFlow} />
        </ResponsiveCard>
    </div>
</MobilePageLayout>
