<script lang="ts">
    import type { ChartConfig } from '@components/ui/atoms/chart';
    import type { BalanceTrendReportData } from '@type/generated';

    import { page, router } from '@inertiajs/svelte';
    import { LineChart } from 'layerchart';
    import { SvelteURLSearchParams } from 'svelte/reactivity';

    import DateTimeHelper from '@utilities/date-time-helper';
    import Formatter from '@utilities/formatter';

    import { ChartContainer, ChartTooltip } from '@components/ui/atoms/chart';
    import { Tabs, TabsList, TabsTrigger } from '@components/ui/atoms/tabs';
    import Skeleton from '@components/ui/skeleton.svelte';

    interface BalancePoint {
        label: string;
        balance: number;
        net_delta: number;
    }

    type Span = '6' | '12' | '24';

    let { trend }: { trend?: BalanceTrendReportData } = $props();

    // Seeds from the URL so a shared or reloaded link keeps its window.
    const initialSpan = new SvelteURLSearchParams(page.url.split('?')[1] ?? '').get('balance');

    let span = $state<Span>(initialSpan === '12' || initialSpan === '24' ? initialSpan : '6');

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

    /** Partial reload — swaps only this card's prop; `data` merges over the live query. */
    function visitSpan(next: string): void {
        span = next as Span;

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
    <div class="mb-3 flex items-center justify-between">
        <div>
            <p class="text-xs text-muted-foreground">Now</p>
            <p class="mt-0.5 font-mono text-sm font-semibold">
                {Formatter.currency(trend.current_total, true)}
            </p>
        </div>

        <Tabs onValueChange={(v) => visitSpan(v)} value={span}>
            <TabsList>
                <TabsTrigger value="6">6M</TabsTrigger>
                <TabsTrigger value="12">12M</TabsTrigger>
                <TabsTrigger value="24">24M</TabsTrigger>
            </TabsList>
        </Tabs>
    </div>

    <ChartContainer class="min-h-55 w-full" config={chartConfig}>
        <LineChart
            data={chartData}
            padding={{ left: 16, bottom: 24 }}
            props={{ yAxis: { format: (v) => Formatter.compactAmount(v) } }}
            series={[{ key: 'balance', color: 'var(--primary)' }]}
            x="label">
            {#snippet tooltip()}
                <ChartTooltip>
                    {#snippet tooltipContent(data, ui)}
                        {@render ui.label(String(data.label))}
                        <div class="grid gap-1.5">
                            {@render ui.row('Balance', Formatter.currency(data.balance, true))}
                            {@render ui.row('Net change', Formatter.currency(data.net_delta, true))}
                        </div>
                    {/snippet}
                </ChartTooltip>
            {/snippet}
        </LineChart>
    </ChartContainer>
{/if}
