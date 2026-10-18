<script lang="ts">
    import type { ChartConfig } from '@components/ui/atoms/chart';
    import type { WeekdayCashflow, WeekdayPatternReportData } from '@type/generated';

    import StatisticDataTier from '@wayfinder/App/Enums/StatisticDataTier';
    import { BarChart, Points, Spline } from 'layerchart';

    import DateTimeHelper from '@utilities/date-time-helper';
    import Formatter from '@utilities/formatter';
    import { renderComponent } from '@utilities/render-helper';

    import CurrencyAmount from '@components/data/currency-amount.svelte';
    import { ChartContainer, ChartTooltip } from '@components/ui/atoms/chart';
    import Skeleton from '@components/ui/skeleton.svelte';

    /** The wire row plus the FE-side display names for axis and tooltip. */
    type WeekdayDatum = WeekdayCashflow & {
        label: string;
        label_long: string;
    };

    let { pattern }: { pattern?: WeekdayPatternReportData } = $props();

    // Labels only — colors ride the series as direct theme tokens, so no
    // `--color-*` aliases get minted for this chart.
    const chartConfig: ChartConfig = {
        current: { label: 'This week' },
    };

    // Tint ladder on the accent anchor, ordered to match the tier domain.
    const tierRange = ['var(--accent)', 'var(--color-accent-400)', 'var(--color-accent-200)'];

    const chartData = $derived(
        (pattern?.data ?? []).map((m): WeekdayDatum => ({
            label: DateTimeHelper.weekdayNameByIndex(m.weekday - 1),
            label_long: DateTimeHelper.weekdayNameByIndex(m.weekday - 1, 'long'),
            tier: m.tier,
            weekday: m.weekday,
            current: m.current,
            baseline: m.baseline,
            deviation: m.deviation,
        }))
    );

    /** Signed percent for the tooltip — early-week days read low (week to date). */
    function deviationLabel(deviation: number): string {
        const sign = deviation > 0 ? '+' : '';

        return `${sign}${Math.round(deviation)}%`;
    }
</script>

{#if !pattern}
    <!-- Deferred prop skeleton — the report streams in after first paint. -->
    <div class="space-y-3">
        <Skeleton class="h-9 w-36" />
        <Skeleton class="h-56 w-full" />
    </div>
{:else if pattern.total_expense === 0}
    <div class="flex flex-col items-center justify-center py-10 text-base-content/40">
        <i class="mb-2 iconify size-8 solar--calendar-bold-duotone"></i>
        <p class="text-sm">No expense data this week</p>
    </div>
{:else}
    <p class="flex items-center justify-end gap-1.5 text-sm text-primary">
        <i class="iconify size-5 text-primary solar--minus-linear"></i>
        3-Mon Avg, Week to date
    </p>

    <ChartContainer
        class={[
            'min-h-50 w-full [&_.lc-axis-tick-label]:fill-secondary-foreground',
            '[&_.lc-rule-y-line:not(.lc-grid-y-rule)]:stroke-accent/70 [&_.lc-rule-y-line:not(.lc-grid-y-rule)]:stroke-1',
        ]}
        config={chartConfig}>
        <BarChart
            axis="x"
            c="tier"
            cDomain={[StatisticDataTier.Top, StatisticDataTier.Second, StatisticDataTier.Rest]}
            cRange={tierRange}
            data={chartData}
            grid={false}
            labels={{ fill: 'var(--secondary-foreground)', placement: 'outside' }}
            padding={{}}
            props={{
                bars: { strokeOpacity: 0.8, stroke: 'var(--secondary)', strokeWidth: 1 },
                labels: {
                    format: (v) => Formatter.compactAmount(v),
                },
                highlight: { opacity: 0.1 },
            }}
            series={[{ key: 'current' }]}
            x="label">
            {#snippet aboveMarks()}
                <Spline stroke="var(--primary)" strokeWidth={1.5} y="baseline" />
                <Points fill="var(--primary)" r={3} y="baseline" />
            {/snippet}

            {#snippet tooltip()}
                <ChartTooltip>
                    {#snippet tooltipContent(data, ui)}
                        {@render ui.label(data.label_long)}

                        <div class="grid gap-1.5">
                            {@render ui.row(
                                'This week',
                                renderComponent(CurrencyAmount, {
                                    value: data.current.toFixed(0),
                                    class: 'font-semibold text-foreground',
                                })
                            )}

                            {@render ui.row(
                                '3 Month Avg',
                                renderComponent(CurrencyAmount, {
                                    value: data.baseline.toFixed(0),
                                    class: 'font-semibold text-foreground',
                                })
                            )}

                            {@render ui.row('Change', deviationLabel(data.deviation))}
                        </div>
                    {/snippet}
                </ChartTooltip>
            {/snippet}
        </BarChart>
    </ChartContainer>
{/if}
