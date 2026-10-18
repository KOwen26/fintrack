<script lang="ts">
    import type { ChartConfig } from '@components/ui/atoms/chart';
    import type { SavingsRateReportData } from '@type/generated';

    import SavingsRateTier from '@wayfinder/App/Enums/SavingsRateTier';
    import { scaleLinear } from 'd3-scale';
    import { AnnotationLine, ArcChart, LineChart, Points } from 'layerchart';

    import DateTimeHelper from '@utilities/date-time-helper';

    import { ChartContainer, ChartTooltip } from '@components/ui/atoms/chart';
    import Skeleton from '@components/ui/skeleton.svelte';

    let { report }: { report?: SavingsRateReportData } = $props();

    const chartConfig: ChartConfig = {
        rate: { label: 'Savings rate' },
    };

    const history = $derived(
        (report?.data ?? []).map((m) => ({
            label: DateTimeHelper.format(DateTimeHelper.parseLocalDay(m.date), 'month-year-short'),
            rate: m.savings_rate,
            tier: m.tier,
        }))
    );

    const target = $derived(report?.target_rate ?? 20);

    /** d3 nice-tick math over the rate domain, anchored to include the target line. Mirrors
        balance-trend-chart: explicit yDomain/ticks because layerchart's createScale copies
        (scale.copy()) strip instance overrides. */
    const yScaleConfig = $derived.by(() => {
        const rates = history.map((d) => d.rate);
        const min = Math.min(0, target, ...rates);
        const max = Math.max(0, target, ...rates);

        if (min === max) {
            return { domain: [min, max] as [number, number], ticks: [min] };
        }

        const scale = scaleLinear().domain([min, max]).nice(5);
        const domain = scale.domain() as [number, number];

        const all = scale.ticks(10);
        const keep = Math.ceil(all.length / 5);

        return {
            domain,
            // Thin to roughly maxTicks, always keeping both domain edges.
            ticks: all.filter((_, i) => i % keep === 0 || i === all.length - 1),
        };
    });

    const current = $derived(report?.current_rate ?? 0);
    const average = $derived(report?.average_rate ?? 0);

    // Gauge: current vs average, capped at 100% for the arc (negative shows as 0).
    const gaugeValue = $derived(Math.max(0, Math.min(100, current)));

    // Gauge arc color follows the current month's tier (fallback: caution).
    const gaugeColor = $derived(tierColor(report?.data.at(-1)?.tier ?? SavingsRateTier.Caution));

    function tierColor(tier: string): string {
        switch (tier) {
            case SavingsRateTier.Healthy:
                return 'var(--success)';
            case SavingsRateTier.Caution:
                return 'var(--warning)';
            default:
                return 'var(--error)';
        }
    }
</script>

{#if !report}
    <div class="space-y-3">
        <Skeleton class="h-32 w-full" />
        <Skeleton class="h-56 w-full" />
    </div>
{:else}
    <!-- Gauge + meta strip -->
    <div class="space-y-6">
        <div class="flex items-center justify-center">
            <ChartContainer class="h-40 w-40" config={chartConfig}>
                <ArcChart
                    c={() => gaugeColor}
                    cornerRadius={4}
                    data={[{ value: gaugeValue }]}
                    innerRadius={0.6}
                    maxValue={100}
                    outerRadius={0.9}
                    padAngle={0.02}
                    range={[-120, 120]}
                    value="value" />
            </ChartContainer>
        </div>

        <div class="grid grid-cols-2 gap-3 text-sm">
            <div class="rounded-lg bg-muted/50 p-2.5">
                <p class="text-xs text-muted-foreground">Current month</p>
                <p
                    style:color={tierColor(report.data.at(-1)?.tier ?? 'caution')}
                    class="mt-1 font-mono text-lg font-semibold">
                    {current.toFixed(1)}%
                </p>
            </div>
            <div class="rounded-lg bg-muted/50 p-2.5">
                <p class="text-xs text-muted-foreground">Period average</p>
                <p class="mt-1 font-mono text-lg font-semibold text-foreground">
                    {average.toFixed(1)}%
                </p>
            </div>
            <div class="rounded-lg bg-muted/50 p-2.5">
                <p class="text-xs text-muted-foreground">Months on target</p>
                <p class="mt-1 font-mono text-lg font-semibold text-foreground">
                    {report.months_on_target} / {report.data.length}
                </p>
            </div>
            <div class="rounded-lg bg-muted/50 p-2.5">
                <p class="text-xs text-muted-foreground">Current streak</p>
                <p class="mt-1 font-mono text-lg font-semibold text-foreground">
                    {report.current_streak} mo
                    <span class="ml-1 text-xs text-muted-foreground"
                        >(best {report.longest_streak})</span>
                </p>
            </div>
            <div class="rounded-lg bg-muted/50 p-2.5">
                <p class="text-xs text-muted-foreground">Best month</p>
                <p class="mt-1 font-mono text-sm font-semibold text-success">
                    {report.best_rate.toFixed(1)}%
                </p>
            </div>
            <div class="rounded-lg bg-muted/50 p-2.5">
                <p class="text-xs text-muted-foreground">Worst month</p>
                <p class="mt-1 font-mono text-sm font-semibold text-error">
                    {report.worst_rate.toFixed(1)}%
                </p>
            </div>
        </div>
    </div>

    <!-- History line -->
    <ChartContainer class="mb-10 min-h-50 w-full" config={chartConfig}>
        <LineChart
            c="tier"
            cDomain={[SavingsRateTier.Healthy, SavingsRateTier.Caution, SavingsRateTier.Negative]}
            cRange={['var(--success)', 'var(--warning)', 'var(--error)']}
            data={history}
            padding={{ left: 36, bottom: 0, top: 8, right: 8 }}
            props={{
                grid: { yTicks: yScaleConfig?.ticks },
                yAxis: {
                    ticks: yScaleConfig?.ticks,
                    format: (v) => `${v}%`,
                },
                xAxis: {
                    tickOcclusion: true,
                    tickLabelProps: { rotate: -45, textAnchor: 'end' },
                },
                spline: { strokeWidth: 2 },
            }}
            series={[{ key: 'rate', color: 'var(--primary)' }]}
            x="label"
            y="rate"
            yDomain={yScaleConfig?.domain}>
            {#snippet aboveMarks()}
                <AnnotationLine
                    label="Target"
                    props={{
                        line: { dashArray: [4, 4], stroke: 'var(--primary)', strokeWidth: 1.5 },
                    }}
                    y={target} />
                <Points r={4} y="rate" />
            {/snippet}

            {#snippet tooltip()}
                <ChartTooltip>
                    {#snippet tooltipContent(data, ui)}
                        {@render ui.label(String(data.label))}

                        <div class="grid gap-1.5">
                            {@render ui.row('Savings rate', `${data.rate.toFixed(1)}%`)}
                            {@render ui.row('Tier', data.tier)}
                        </div>
                    {/snippet}
                </ChartTooltip>
            {/snippet}
        </LineChart>
    </ChartContainer>
{/if}
