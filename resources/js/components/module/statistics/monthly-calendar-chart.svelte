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

    let mode = $state<Mode>('net');

    interface CalendarFill {
        /** Cell background — SVG fill / CSS background. */
        fill: string;
        /** Ink for the day number + amount on top of that fill. */
        text: string;
    }

    // Step fills as var references — the --calendar-* vars (defined in the
    // grid's <style>) own the colors; override them per theme or instance.
    // Index = intensity step (0–3), lightest tint up to the full token.
    const incomeFills: CalendarFill[] = [
        { fill: 'var(--calendar-income-tint-1)', text: 'var(--calendar-text-1)' },
        { fill: 'var(--calendar-income-tint-2)', text: 'var(--calendar-text-2)' },
        { fill: 'var(--calendar-income-tint-3)', text: 'var(--calendar-text-3)' },
        { fill: 'var(--calendar-income-full)', text: 'var(--calendar-income-text-full)' },
    ];

    const expenseFills: CalendarFill[] = [
        { fill: 'var(--calendar-expense-tint-1)', text: 'var(--calendar-text-1)' },
        { fill: 'var(--calendar-expense-tint-2)', text: 'var(--calendar-text-2)' },
        { fill: 'var(--calendar-expense-tint-3)', text: 'var(--calendar-text-3)' },
        { fill: 'var(--calendar-expense-full)', text: 'var(--calendar-expense-text-full)' },
    ];

    const noActivityFill: CalendarFill = {
        fill: 'var(--calendar-none)',
        text: 'var(--calendar-none-text)',
    };

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

    function fillFor(cell: CalendarCell): CalendarFill {
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
    <div class="mb-3 flex justify-between">
        <div>
            <!-- TODO MONTH PREV NEXT -->
        </div>

        <Tabs onValueChange={(v) => (mode = v)} value={mode}>
            <TabsList>
                <TabsTrigger class="min-w-15" value="net">Net</TabsTrigger>
                <TabsTrigger class="min-w-15" value="income">Income</TabsTrigger>
                <TabsTrigger class="min-w-15" value="expense">Expense</TabsTrigger>
            </TabsList>
        </Tabs>
    </div>

    <!-- Square region: the tallest month (6 week rows + header = 7) fills it exactly; shorter months keep a stable card height with bottom slack. -->
    <ChartContainer class="aspect-square w-full" config={{}}>
        <Chart class="relative" data={chartData} x="date">
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

            <div class="absolute bottom-5 w-full">
                {#if monthlyFlow.data.length === 0}
                    <div
                        class="flex flex-col items-center justify-center py-6 text-base-content/40">
                        <i class="mb-2 iconify size-8 solar--calendar-bold-duotone"></i>
                        <p class="text-sm">{emptyMessage}</p>
                    </div>
                {:else}
                    <div
                        class="mt-3 flex flex-wrap items-center justify-center gap-2 text-xs text-muted-foreground">
                        <span>Less</span>
                        {#each legendFills as entry (entry.fill)}
                            <span style:background={entry.fill} class="size-3 rounded-sm"></span>
                        {/each}
                        <span>More</span>
                        <span class="mx-1 size-3 rounded-sm bg-muted"></span>
                        <span>No activity</span>
                    </div>
                {/if}
            </div>
        </Chart>
    </ChartContainer>
</div>
