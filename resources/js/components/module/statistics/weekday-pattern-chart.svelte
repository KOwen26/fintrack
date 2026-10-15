<script lang="ts">
    import type { ChartConfig } from '@components/ui/atoms/chart';
    import type { WeekdayPatternReportData } from '@type/generated';

    import { BarChart } from 'layerchart';

    import Formatter from '@utilities/formatter';

    import { ChartContainer, ChartTooltip } from '@components/ui/atoms/chart';
    import Skeleton from '@components/ui/skeleton.svelte';

    // Matches the grid's Monday-first WEEKDAYS ordering (ISO weekday 1–7).
    const WEEKDAYS = ['Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat', 'Sun'];

    interface WeekdayDatum {
        label: string;
        tier: 'top' | 'second' | 'rest';
        expense: number;
        average: number;
        share: number;
        count: number;
    }

    let { pattern }: { pattern?: WeekdayPatternReportData } = $props();

    // Labels only — colors ride the series as direct theme tokens, so no
    // `--color-*` aliases get minted for this chart.
    const chartConfig: ChartConfig = {
        average: { label: 'Average expense' },
    };

    // Rank by average desc (1 = heaviest) — feeds the data-driven fill.
    const averageRanks = $derived.by(() => {
        const sorted = [...(pattern?.data ?? [])].sort((a, b) => b.average - a.average);

        return new Map(sorted.map((m, i) => [m.weekday, i + 1]));
    });

    // Tint ladder on the error anchor, ordered to match cDomain — heaviest
    // day at full strength, second at 75%, the rest at 50% onto the card.
    const tierRange = ['var(--error)', 'var(--color-error-400)', 'var(--color-error-200)'];

    const chartData = $derived(
        (pattern?.data ?? []).map((m): WeekdayDatum => {
            const rank = averageRanks.get(m.weekday) ?? Infinity;

            return {
                label: WEEKDAYS[m.weekday - 1] ?? String(m.weekday),
                tier: rank === 1 ? 'top' : rank === 2 ? 'second' : 'rest',
                expense: m.expense,
                average: m.average,
                share: m.share,
                count: m.count,
            };
        })
    );

    const peakLabel = $derived(
        (pattern?.peak_weekday && WEEKDAYS[pattern.peak_weekday - 1]) || null
    );
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
        <p class="text-sm">No expense data for this period</p>
    </div>
{:else}
    <!-- <div class="mb-3 flex items-center justify-between">
        <div>
            <p class="text-xs text-muted-foreground">Heaviest day</p>
            <p class="mt-0.5 font-mono text-sm font-semibold">
                {peakLabel ?? '—'}
            </p>
        </div>

        <div class="text-right">
            <p class="text-xs text-muted-foreground">Total</p>
            <p class="mt-0.5 font-mono text-sm font-semibold">
                {Formatter.currency(pattern.total_expense, true)}
            </p>
        </div>
    </div> -->

    <ChartContainer
        class="min-h-50 w-full px-5 [&_.lc-axis-tick-label]:fill-secondary-foreground"
        config={chartConfig}>
        <BarChart
            c="tier"
            cDomain={['top', 'second', 'rest']}
            cRange={tierRange}
            data={chartData}
            padding={{ left: 16, bottom: 24 }}
            props={{
                bars: { strokeOpacity: 0.8, stroke: 'var(--color-error)', strokeWidth: 1 },
                yAxis: {
                    format: (v) => Formatter.compactAmount(v),
                },
            }}
            series={[{ key: 'average' }]}
            x="label">
            {#snippet tooltip()}
                <ChartTooltip>
                    {#snippet tooltipContent(data, ui)}
                        {@render ui.label(String(data.label))}
                        <div class="grid gap-1.5">
                            {@render ui.row(
                                'Average',
                                Formatter.currency(data.average.toFixed(0), true)
                            )}
                            {@render ui.row('Total', Formatter.currency(data.expense, true))}
                            {@render ui.row('Share', `${data.share}%`)}
                            {@render ui.row('Transactions', String(data.count))}
                        </div>
                    {/snippet}
                </ChartTooltip>
            {/snippet}
        </BarChart>
    </ChartContainer>
{/if}
