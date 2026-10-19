<script lang="ts">
    import type { CalendarBackground } from '@components/module/statistics/monthly-calendar-grid.svelte';
    import type { ChartConfig } from '@components/ui/atoms/chart';
    import type { MonthlyCashflowReportData } from '@type/generated';

    import { router } from '@inertiajs/svelte';
    import { addMonths, endOfMonth, format, isSameMonth } from 'date-fns';
    import { Chart } from 'layerchart';

    import DateTimeHelper from '@utilities/date-time-helper';
    import Formatter from '@utilities/formatter';
    import { renderComponent } from '@utilities/render-helper';

    import CurrencyAmount from '@components/data/currency-amount.svelte';
    import MonthlyCalendarGrid, {
        calendarRamp,
    } from '@components/module/statistics/monthly-calendar-grid.svelte';
    import { ChartContainer, ChartTooltip } from '@components/ui/atoms/chart';
    import { Tabs, TabsList, TabsTrigger } from '@components/ui/atoms/tabs';
    import Button from '@components/ui/button.svelte';
    import Skeleton from '@components/ui/skeleton.svelte';

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
        monthlyCashflow,
        onDayClick,
        emptyMessage = 'No transaction data this month',
    }: {
        monthlyCashflow?: MonthlyCashflowReportData;
        onDayClick?: (date: Date) => void;
        emptyMessage?: string;
    } = $props();

    let mode = $state<Mode>('net');

    const chartConfig: ChartConfig = {
        income: { label: 'Income', color: 'var(--success)' },
        expense: { label: 'Expense', color: 'var(--error)' },
        'income-foreground': { color: 'var(--success-foreground)' },
        'expense-foreground': { color: 'var(--error-foreground)' },
        none: { color: 'var(--muted)' },
        'none-foreground': { color: 'color-mix(in oklab, var(--foreground) 55%, transparent)' },
    };

    // Index = intensity step (0–3), lightest tint up to the full anchor.
    const incomeBackgrounds = calendarRamp('var(--color-income)', 'var(--color-income-foreground)');
    const expenseBackgrounds = calendarRamp(
        'var(--color-expense)',
        'var(--color-expense-foreground)'
    );

    const noActivityBackground: CalendarBackground = {
        background: 'var(--color-none)',
        foreground: 'var(--color-none-foreground)',
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

    // Month window from the server's period (`from` is start-of-month, Y-m-d).
    const monthStart = $derived(
        monthlyCashflow ? DateTimeHelper.parseLocalDay(monthlyCashflow.from) : new Date()
    );
    const monthEnd = $derived(endOfMonth(monthStart));

    const chartData = $derived(
        (monthlyCashflow?.data ?? []).map((d): DayDatum => ({
            date: DateTimeHelper.parseLocalDay(d.date),
            income: d.income,
            expense: d.expense,
            net: d.net,
        }))
    );

    // Scale anchors come from the report — the server already aggregated them.
    const maxAbsNet = $derived(monthlyCashflow?.max_abs_net ?? 0);
    const maxIncome = $derived(monthlyCashflow?.max_income ?? 0);
    const maxExpense = $derived(monthlyCashflow?.max_expense ?? 0);

    function backgroundFor(cell: CalendarCell): CalendarBackground {
        const d = cell.data;

        if (!('net' in d)) return noActivityBackground;

        if (mode === 'net') {
            if (d.net === 0) return noActivityBackground;

            const step = stepOf(Math.abs(d.net), maxAbsNet);

            return d.net > 0 ? incomeBackgrounds[step] : expenseBackgrounds[step];
        }

        if (mode === 'income') {
            if (d.income <= 0) return noActivityBackground;

            return incomeBackgrounds[stepOf(d.income, maxIncome)];
        }

        if (d.expense <= 0) return noActivityBackground;

        return expenseBackgrounds[stepOf(d.expense, maxExpense)];
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

    const legendBackgrounds = $derived(mode === 'expense' ? expenseBackgrounds : incomeBackgrounds);

    const isCurrentMonth = $derived(isSameMonth(monthStart, new Date()));

    /** Partial reload — swaps only the monthlyCashflow prop; `data` merges over the live query. */
    function visitMonth(delta: number): void {
        const month = format(addMonths(monthStart, delta), 'yyyy-MM');

        router.reload({ only: ['monthlyCashflow'], data: { month } });
    }
</script>

{#if !monthlyCashflow}
    <!-- Deferred prop skeleton — the report streams in after first paint. -->
    <div class="space-y-3">
        <Skeleton class="mx-auto h-6 w-40" />
        <Skeleton class="aspect-square w-full" />
    </div>
{:else}
    <div class="w-full">
        <div class="mb-3 flex items-center justify-between">
            <div class="flex items-center gap-1">
                <Button
                    aria-label="Previous month"
                    onclick={() => visitMonth(-1)}
                    size="icon-sm"
                    variant="ghost">
                    <i class="iconify size-5 solar--alt-arrow-left-line-duotone"></i>
                </Button>

                <span class=" text-center text-sm font-medium">
                    {DateTimeHelper.format(monthStart, 'month-year')}
                </span>

                <Button
                    aria-label="Next month"
                    disabled={isCurrentMonth}
                    onclick={() => visitMonth(1)}
                    size="icon-sm"
                    variant="ghost">
                    <i class="iconify size-5 solar--alt-arrow-right-line-duotone"></i>
                </Button>
            </div>

            <Tabs onValueChange={(v) => (mode = v)} value={mode}>
                <TabsList>
                    <TabsTrigger class="min-w-8 p-1" aria-label="Net" value="net">
                        <i class="iconify size-5 solar--sort-vertical-line-duotone"></i>
                    </TabsTrigger>
                    <TabsTrigger class="min-w-8 p-1" aria-label="Income" value="income">
                        <i
                            class="iconify size-5 text-success solar--arrow-to-down-right-line-duotone"
                        ></i>
                    </TabsTrigger>
                    <TabsTrigger class="min-w-8 p-1" aria-label="Expense" value="expense">
                        <i class="iconify size-5 text-error solar--arrow-to-top-left-line-duotone"
                        ></i>
                    </TabsTrigger>
                </TabsList>
            </Tabs>
        </div>

        <!-- Square region: the tallest month (6 week rows + header = 7) fills it exactly; shorter months keep a stable card height with bottom slack. -->
        <ChartContainer class="aspect-square w-full" config={chartConfig}>
            <Chart class="relative" data={chartData} x="date">
                <MonthlyCalendarGrid
                    {backgroundFor}
                    {labelFor}
                    {monthEnd}
                    {monthStart}
                    {onDayClick} />

                <ChartTooltip>
                    {#snippet tooltipContent(data, ui)}
                        {@render ui.label(DateTimeHelper.format(data.date, 'date'))}

                        {#if 'net' in data}
                            <div class="grid gap-1.5">
                                {@render ui.row(
                                    'Income',
                                    renderComponent(CurrencyAmount, {
                                        value: data.income,
                                        class: 'font-semibold text-foreground',
                                    })
                                )}
                                {@render ui.row(
                                    'Expense',
                                    renderComponent(CurrencyAmount, {
                                        value: data.expense,
                                        class: 'font-semibold text-foreground',
                                    })
                                )}
                                {@render ui.row(
                                    'Net',
                                    renderComponent(CurrencyAmount, {
                                        value: data.net,
                                        class: 'font-semibold text-foreground',
                                    })
                                )}
                            </div>
                        {/if}
                    {/snippet}
                </ChartTooltip>

                <div class="absolute bottom-0 w-full">
                    {#if monthlyCashflow.data.length === 0}
                        <div
                            class="flex flex-col items-center justify-center py-6 text-base-content/40">
                            <i class="mb-2 iconify size-8 solar--calendar-bold-duotone"></i>
                            <p class="text-sm">{emptyMessage}</p>
                        </div>
                    {:else}
                        <div
                            class="mt-3 flex flex-wrap items-center justify-center gap-2 text-xs text-muted-foreground">
                            <span>Less</span>
                            {#each legendBackgrounds as entry (entry.background)}
                                <span style:background={entry.background} class="size-3 rounded-sm"
                                ></span>
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
{/if}
