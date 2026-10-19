<script lang="ts">
    import type { ChartConfig } from '@components/ui/atoms/chart';
    import type { SavingsRateReportData } from '@type/generated';

    import SavingsRateTier from '@wayfinder/App/Enums/SavingsRateTier';
    import { AnnotationLine, Arc, ArcChart, Group, LineChart, Points, Text } from 'layerchart';

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
            rate: m.rate,
            tier: m.tier,
        }))
    );

    const target = $derived(report?.target_rate);

    const yScaleConfig = $derived.by(() => {
        const Y_MAX = 100;
        const Y_MIN = -200;
        const Y_STEP = 50;

        const dataMin = history.length > 0 ? Math.min(...history.map((d) => d.rate)) : Y_MIN;
        const min = Math.max(Y_MIN, dataMin);
        const step = dataMin > -100 ? 25 : Y_STEP;
        const start = Math.floor(min / step) * step;

        const ticks: number[] = [];

        for (let v = start; v <= Y_MAX; v += step) {
            ticks.push(v);
        }

        return { domain: [start, Y_MAX] as [number, number], ticks };
    });

    const current = $derived(report?.current_rate ?? 0);
    const average = $derived(report?.average_rate ?? 0);

    // Gauge: current vs average. The dial's domain is [-100, 100], so a negative
    // month is "off the scale": the arc pegs full-span (speedometer-past-the-limit)
    // while the center text shows the true negative number.
    const gaugeValue = $derived(Math.max(0, Math.min(100, current)));
    const isNegative = $derived(current < 0);
    const arcValue = $derived(isNegative ? 100 : gaugeValue);

    // Arc fill + current-value color follow the month's tier. Zero or a missing
    // tier reads as "no data" and goes muted instead of a tier color.
    const gaugeColor = $derived.by(() => {
        const tier = report?.data.at(-1)?.tier;

        return current === 0 || tier == null ? 'var(--muted-foreground)' : tierColor(tier);
    });

    function tierColor(tier: string | null): string {
        if (tier == null) {
            return 'var(--muted-foreground)';
        }

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
    <div class="space-y-6">
        <!-- Gauge + meta strip -->
        <div class="grid grid-cols-10 items-center">
            <div class="col-span-6">
                <ChartContainer class="aspect-square" config={chartConfig}>
                    <ArcChart padding={{ left: -5 }}>
                        {#snippet marks()}
                            <Group y={20}>
                                <Arc
                                    cornerRadius={20}
                                    domain={[0, 100]}
                                    fill={gaugeColor}
                                    innerRadius={70}
                                    outerRadius={86}
                                    range={[-120, 120]}
                                    track={{ class: 'fill-muted stroke-muted-foreground/25' }}
                                    value={arcValue} />

                                <Text
                                    class="text-sm! font-semibold text-foreground/70"
                                    textAnchor="middle"
                                    value="Current Month"
                                    verticalAnchor="middle"
                                    x={0}
                                    y={-32} />

                                <Text
                                    class="text-3xl! font-bold tabular-nums"
                                    fill={gaugeColor}
                                    textAnchor="middle"
                                    value={`${current.toFixed(0)}%`}
                                    verticalAnchor="middle" />

                                <Text
                                    class="fill-muted-foreground text-sm! font-medium tabular-nums"
                                    textAnchor="middle"
                                    value={`Avg ${average.toFixed(1)}%`}
                                    verticalAnchor="middle"
                                    x={0}
                                    y={24} />
                            </Group>
                        {/snippet}
                    </ArcChart>
                </ChartContainer>
            </div>
            <div class="col-span-4 space-y-3 text-sm">
                <div>
                    <h6 class="font-medium text-muted-foreground">Best / Worst</h6>
                    <div class="inline-flex items-center text-muted-foreground">
                        <span class="font-semibold text-success">
                            {report.best_rate.toFixed(2)}%
                        </span>,
                        <span class="ml-1 font-semibold text-error">
                            {report.worst_rate.toFixed(2)}%
                        </span>
                    </div>
                </div>
                <div>
                    <h6 class="font-medium text-muted-foreground">Current / Best Streak</h6>
                    <p class="">
                        {report.current_streak}
                        {report.longest_streak > 0 ? `/ ${report.longest_streak}` : ``} Months
                    </p>
                </div>
            </div>
        </div>
        <!-- History line -->
        <ChartContainer class="mb-10 min-h-50 w-full" config={chartConfig}>
            <LineChart
                c="tier"
                cDomain={[
                    SavingsRateTier.Healthy,
                    SavingsRateTier.Caution,
                    SavingsRateTier.Negative,
                ]}
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
                    // Per-segment tier runs (Spline docs "Per-segment styling"):
                    // consecutive months of the same tier share one colored path.
                    spline: {
                        strokeWidth: 2,
                        stroke: (d) => tierColor(d.tier),
                    },
                }}
                series={[{ key: 'rate' }]}
                x="label"
                y="rate"
                yDomain={yScaleConfig?.domain}>
                {#snippet aboveMarks()}
                    <AnnotationLine
                        label="Target"
                        props={{
                            line: {
                                dashArray: [4, 4],
                                strokeWidth: 1.5,
                            },
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
    </div>
{/if}
