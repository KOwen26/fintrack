<script lang="ts">
    import type { MonthlyFlowReportData } from '@type/generated';

    import { Chart, Tooltip } from 'layerchart';

    import Formatter from '@utilities/formatter';

    import MonthlyCalendarGrid from '@components/module/statistics/monthly-calendar-grid.svelte';
    import { ChartContainer } from '@components/ui/atoms/chart';
    import { Tabs, TabsList, TabsTrigger } from '@components/ui/atoms/tabs';

    interface DayDatum {
        date: Date;
        income: number;
        expense: number;
        net: number;
    }

    interface CalendarCell {
        x: number;
        y: number;
        color: unknown;
        data: any;
        date: Date;
    }

    type Mode = 'net' | 'income' | 'expense';

    let {
        monthlyFlow,
        onDayClick,
        emptyMessage = 'No transaction data this month',
    }: {
        monthlyFlow: MonthlyFlowReportData;
        onDayClick?: (date: Date) => void;
        emptyMessage?: string;
    } = $props();

    let mode: Mode = $state('net');

    // Intensity steps: the success/error tokens mixed onto the card surface,
    // lightest tint for small days up to the full token for the month's max.
    const STEP_MIX = [25, 50, 75, 100];

    function tokenMix(token: string, pct: number): string {
        return `color-mix(in oklab, var(${token}) ${pct}%, var(--card))`;
    }

    const incomeFills = STEP_MIX.map((pct) => tokenMix('--success', pct));
    const expenseFills = STEP_MIX.map((pct) => tokenMix('--error', pct));
    const noActivityFill = 'var(--muted)';

    /**
     * √-compressed step (0–3) relative to the month's max. Square-root scaling
     * keeps a single rent-sized day from compressing every other day into the
     * lightest tint — a day at ¼ of the max lands mid-scale instead of ¼-scale.
     */
    function stepOf(value: number, max: number): number {
        if (value <= 0 || max <= 0) return 0;

        const t = Math.sqrt(value / max);

        if (t > 0.75) return 3;
        if (t > 0.5) return 2;
        if (t > 0.25) return 1;

        return 0;
    }

    // Month window from the server's period (`from` is start-of-month, DATE_ATOM).
    const monthStart = $derived(new Date(`${monthlyFlow.from.slice(0, 10)}T00:00:00`));
    const monthEnd = $derived(new Date(monthStart.getFullYear(), monthStart.getMonth() + 1, 0));

    const chartData = $derived(
        monthlyFlow.data.map((d): DayDatum => ({
            date: new Date(`${d.date}T00:00:00`),
            income: d.income,
            expense: d.expense,
            net: d.net,
        }))
    );

    const maxAbsNet = $derived(Math.max(0, ...chartData.map((d) => Math.abs(d.net))));
    const maxIncome = $derived(Math.max(0, ...chartData.map((d) => d.income)));
    const maxExpense = $derived(Math.max(0, ...chartData.map((d) => d.expense)));

    function fillFor(cell: CalendarCell): string {
        const d = cell.data;

        if (!('net' in d)) return noActivityFill;

        if (mode === 'net') {
            if (d.net === 0) return noActivityFill;

            const step = stepOf(Math.abs(d.net), maxAbsNet);

            return d.net > 0 ? incomeFills[step] : expenseFills[step];
        }

        if (mode === 'income') {
            if (d.income <= 0) return noActivityFill;

            return incomeFills[stepOf(d.income, maxIncome)];
        }

        if (d.expense <= 0) return noActivityFill;

        return expenseFills[stepOf(d.expense, maxExpense)];
    }

    /**
     * Compact amount for the day cell, following the active mode — net mode
     * shows the signed net, income/expense modes their totals. Null when there
     * is nothing worth printing (no activity, or zero for the mode).
     */
    function labelFor(cell: CalendarCell): string | null {
        const d = cell.data;

        if (!('net' in d)) return null;

        const value = mode === 'net' ? d.net : mode === 'income' ? d.income : d.expense;

        return value === 0 ? null : Formatter.compactAmount(value);
    }

    const legendFills = $derived(mode === 'expense' ? expenseFills : incomeFills);
</script>

<div class="w-full">
    <div class="mb-3 flex justify-center">
        <Tabs onValueChange={(v: string) => (mode = v as Mode)} value={mode}>
            <TabsList>
                <TabsTrigger value="net">Net</TabsTrigger>
                <TabsTrigger value="income">Income</TabsTrigger>
                <TabsTrigger value="expense">Expense</TabsTrigger>
            </TabsList>
        </Tabs>
    </div>

    <!-- Square region: the tallest month (6 week rows + header = 7) fills it
        exactly; shorter months keep a stable card height with bottom slack. -->
    <ChartContainer class="aspect-square w-full" config={{}}>
        <Chart data={chartData} x="date">
            <MonthlyCalendarGrid {fillFor} {labelFor} {monthEnd} {monthStart} {onDayClick} />

            <Tooltip.Root>
                {#snippet children({ data })}
                    <Tooltip.Header format="day" value={data.date} />

                    {#if 'net' in data}
                        <Tooltip.List>
                            <Tooltip.Item
                                label="Income"
                                value={Formatter.currency(data.income, true)} />
                            <Tooltip.Item
                                label="Expense"
                                value={Formatter.currency(data.expense, true)} />
                            <Tooltip.Item label="Net" value={Formatter.currency(data.net, true)} />
                        </Tooltip.List>
                    {/if}
                {/snippet}
            </Tooltip.Root>
        </Chart>
    </ChartContainer>

    {#if monthlyFlow.data.length === 0}
        <div class="flex flex-col items-center justify-center py-6 text-base-content/40">
            <i class="mb-2 iconify size-8 solar--calendar-bold-duotone"></i>
            <p class="text-sm">{emptyMessage}</p>
        </div>
    {:else}
        <div
            class="mt-3 flex flex-wrap items-center justify-center gap-2 text-xs text-muted-foreground">
            <span>Less</span>
            {#each legendFills as fill (fill)}
                <span style:background={fill} class="size-3 rounded-[3px]"></span>
            {/each}
            <span>More</span>
            <span class="mx-1 size-3 rounded-[3px] bg-muted"></span>
            <span>No activity</span>
        </div>
    {/if}
</div>
