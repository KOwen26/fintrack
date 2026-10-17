<script lang="ts">
    import type { PeriodPreset } from '@components/module/period-preset-select.svelte';
    import type { ChartConfig } from '@components/ui/atoms/chart';
    import type { BalanceTrendReportData } from '@type/generated';

    import { page, router } from '@inertiajs/svelte';
    import DatePeriodPreset from '@wayfinder/App/Enums/DatePeriodPreset';
    import { scaleLinear } from 'd3-scale';
    import { LineChart, Points } from 'layerchart';
    import { SvelteURLSearchParams } from 'svelte/reactivity';

    import DateTimeHelper from '@utilities/date-time-helper';
    import Formatter from '@utilities/formatter';
    import { renderComponent } from '@utilities/render-helper';

    import CurrencyAmount from '@components/data/currency-amount.svelte';
    import PeriodPresetSelect from '@components/module/period-preset-select.svelte';
    import { ChartContainer, ChartTooltip } from '@components/ui/atoms/chart';
    import Skeleton from '@components/ui/skeleton.svelte';

    interface BalancePoint {
        label: string;
        balance: number;
        net_delta: number;
    }

    let { trend }: { trend?: BalanceTrendReportData } = $props();

    const PRESET_VALUES = new Set<string>(Object.values(DatePeriodPreset));

    // Seeds from the URL so a shared or reloaded link keeps its window.
    const initialPreset = new SvelteURLSearchParams(page.url.split('?')[1] ?? '').get('balance');

    let selected = $state<PeriodPreset>(
        initialPreset && PRESET_VALUES.has(initialPreset)
            ? (initialPreset as PeriodPreset)
            : DatePeriodPreset.Last6Months
    );

    // Labels only — colors ride the series as direct theme tokens, so no
    // `--color-*` aliases get minted for this chart.
    const chartConfig: ChartConfig = {
        balance: { label: 'Balance' },
    };

    const chartData = $derived(
        (trend?.data ?? []).map((m): BalancePoint => ({
            label: DateTimeHelper.format(DateTimeHelper.parseLocalDay(m.date), 'month-year-short'),
            balance: m.balance,
            net_delta: m.net_delta,
        }))
    );

    /** d3 does the nice-tick math; the thinning is ours because d3's dense step (2M on a
        16M span) overshoots what a card-height axis should label.
        The results must be passed as explicit yDomain/ticks — layerchart copies scales via createScale (scale.copy()), which strips instance overrides, so a custom-ticked scale object cannot carry them through. */
    const yScaleConfig = $derived.by(() => {
        const min = Math.min(0, ...chartData.map((d) => d.balance));
        const max = Math.max(0, ...chartData.map((d) => d.balance));

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

    /** Partial reload — swaps only this card's prop; the backend resolves the preset into a rolling span. */
    function visitPreset(next: PeriodPreset): void {
        selected = next;

        router.reload({ only: ['balanceTrend'], data: { balance: next } });
    }
</script>

{#if !trend}
    <!-- Deferred prop skeleton — the report streams in after first paint. -->
    <div class="space-y-3">
        <Skeleton class="h-9 w-36" />
        <Skeleton class="h-56 w-full" />
    </div>
{:else}
    <div class="mb-3 flex justify-end">
        <PeriodPresetSelect
            labelVariant="short"
            onValueChange={visitPreset}
            preset="rolling"
            showQuickPicksInDropdown
            bind:value={selected} />
    </div>

    <ChartContainer class="mb-10 min-h-50 w-full" config={chartConfig}>
        <LineChart
            data={chartData}
            padding={{ left: 44, bottom: 0, top: 0, right: 0 }}
            props={{
                grid: { yTicks: yScaleConfig?.ticks },
                yAxis: {
                    ticks: yScaleConfig?.ticks,
                    format: (v) => Formatter.compactAmount(v),
                },
                xAxis: {
                    tickOcclusion: true,
                    tickLabelProps: { rotate: -45, textAnchor: 'end' },
                },
                spline: {
                    strokeWidth: 2,
                },
            }}
            series={[{ key: 'balance', color: 'var(--primary)' }]}
            x="label"
            yDomain={yScaleConfig?.domain}>
            {#snippet aboveMarks()}
                <Points fill="var(--primary)" r={5} y="balance" />
            {/snippet}

            {#snippet tooltip()}
                <ChartTooltip>
                    {#snippet tooltipContent(data, ui)}
                        {@render ui.label(String(data.label))}

                        <div class="grid gap-1.5">
                            {@render ui.row(
                                'Balance',
                                renderComponent(CurrencyAmount, { value: data.balance })
                            )}
                            {@render ui.row(
                                'Net change',
                                renderComponent(CurrencyAmount, { value: data.net_delta })
                            )}
                        </div>
                    {/snippet}
                </ChartTooltip>
            {/snippet}
        </LineChart>
    </ChartContainer>
{/if}
