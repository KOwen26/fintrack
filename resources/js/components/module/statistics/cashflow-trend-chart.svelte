<script lang="ts">
    import type { PeriodPreset } from '@components/module/period-preset-select.svelte';
    import type { ChartConfig } from '@components/ui/atoms/chart';
    import type { CashflowTrendReportData } from '@type/generated';

    import { page, router } from '@inertiajs/svelte';
    import DatePeriodPreset from '@wayfinder/App/Enums/DatePeriodPreset';
    import { BarChart } from 'layerchart';
    import { SvelteURLSearchParams } from 'svelte/reactivity';

    import DateTimeHelper from '@utilities/date-time-helper';
    import Formatter from '@utilities/formatter';

    import PeriodPresetSelect from '@components/module/period-preset-select.svelte';
    import { ChartContainer, ChartTooltip } from '@components/ui/atoms/chart';
    import Skeleton from '@components/ui/skeleton.svelte';

    interface TrendMonth {
        label: string;
        income: number;
        expense: number;
        net: number;
        surplus_rate: number;
    }

    let { trend }: { trend?: CashflowTrendReportData } = $props();

    const PRESET_VALUES = new Set<string>(Object.values(DatePeriodPreset));

    // Seeds from the URL so a shared or reloaded link keeps its window.
    const initialPreset = new SvelteURLSearchParams(page.url.split('?')[1] ?? '').get('trend');

    let selected = $state<PeriodPreset>(
        initialPreset && PRESET_VALUES.has(initialPreset)
            ? (initialPreset as PeriodPreset)
            : DatePeriodPreset.Last6Months
    );

    // Labels only — colors ride the series as direct theme tokens, so no
    // `--color-*` aliases get minted for this chart.
    const chartConfig: ChartConfig = {
        income: { label: 'Income' },
        expense: { label: 'Expense' },
    };

    const chartData = $derived(
        (trend?.data ?? []).map((m): TrendMonth => ({
            label: DateTimeHelper.format(DateTimeHelper.parseLocalDay(m.date), 'month-year-short'),
            income: m.income,
            expense: m.expense,
            net: m.net,
            surplus_rate: m.surplus_rate,
        }))
    );

    /** Spec bands: ≥20% healthy, 0 to below 20% warning, below zero negative. */
    function surplusStatus(rate: number): string {
        if (rate < 0) return 'Negative';

        return rate >= 20 ? 'Healthy' : 'Warning';
    }

    /** Partial reload — swaps only this card's prop; the backend resolves the preset into a window. */
    function visitPreset(next: PeriodPreset): void {
        selected = next;

        router.reload({ only: ['cashflowTrend'], data: { trend: next } });
    }
</script>

{#if !trend}
    <!-- Deferred prop skeleton — the report streams in after first paint. -->
    <div class="space-y-3">
        <div class="grid grid-cols-3 gap-2">
            <Skeleton class="h-12" />
            <Skeleton class="h-12" />
            <Skeleton class="h-12" />
        </div>
        <Skeleton class="h-56 w-full" />
    </div>
{:else}
    <div class="mb-3 flex justify-end">
        <PeriodPresetSelect
            columns={2}
            labelVariant="short"
            onValueChange={visitPreset}
            preset="rolling"
            showQuickPicksInDropdown
            bind:value={selected} />
    </div>

    <!-- <div class="mb-3 grid grid-cols-3 gap-2">
        <div class="rounded-lg bg-muted/50 p-2.5">
            <p class="text-xs text-muted-foreground">Income</p>
            <p class="mt-1 truncate font-mono text-sm font-semibold text-success">
                {Formatter.currency(trend.total_income, true)}
            </p>
        </div>
        <div class="rounded-lg bg-muted/50 p-2.5">
            <p class="text-xs text-muted-foreground">Expense</p>
            <p class="mt-1 truncate font-mono text-sm font-semibold text-error">
                {Formatter.currency(trend.total_expense, true)}
            </p>
        </div>
        <div class="rounded-lg bg-muted/50 p-2.5">
            <p class="text-xs text-muted-foreground">Net</p>
            <p
                class="mt-1 truncate font-mono text-sm font-semibold"
                class:text-error={trend.total_net < 0}
                class:text-success={trend.total_net >= 0}>
                {Formatter.currency(trend.total_net, true)}
            </p>
        </div>
    </div> -->

    <ChartContainer class="mb-10 min-h-50 w-full" config={chartConfig}>
        <BarChart
            data={chartData}
            padding={{ left: 30, bottom: 0, top: 0, right: 0 }}
            props={{
                bars: { strokeWidth: 0 },
                highlight: { opacity: 0.1 },
                yAxis: { format: (v) => Formatter.compactAmount(v) },
                xAxis: {
                    tickOcclusion: true,
                    tickLabelProps: { rotate: -45, textAnchor: 'end' },
                },
            }}
            series={[
                { key: 'income', color: 'var(--success)' },
                { key: 'expense', color: 'var(--error)' },
            ]}
            seriesLayout="group"
            x="label">
            {#snippet tooltip()}
                <ChartTooltip>
                    {#snippet tooltipContent(data, ui)}
                        {@render ui.label(String(data.label))}
                        <div class="grid gap-1.5">
                            {@render ui.row('Income', Formatter.currency(data.income, true))}
                            {@render ui.row('Expense', Formatter.currency(data.expense, true))}
                            {@render ui.row('Net', Formatter.currency(data.net, true))}
                            {@render ui.row(
                                'Surplus rate',
                                `${Math.round(data.surplus_rate)}% · ${surplusStatus(data.surplus_rate)}`
                            )}
                        </div>
                    {/snippet}
                </ChartTooltip>
            {/snippet}
        </BarChart>
    </ChartContainer>
{/if}
