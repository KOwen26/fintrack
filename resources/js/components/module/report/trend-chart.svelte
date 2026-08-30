<script lang="ts">
    import type { ChartConfig } from '@components/ui/atoms/chart';

    import { Axis, Bars, Chart, Tooltip } from 'layerchart';

    import { ChartContainer, ChartTooltip } from '@components/ui/atoms/chart';

    interface TrendMonth {
        year: number;
        month: number;
        income: number;
        expense: number;
        net: number;
        surplus_rate: number;
    }

    let { months }: { months: TrendMonth[] } = $props();

    const chartConfig: ChartConfig = {
        income: { label: 'Income', color: 'hsl(var(--su))' },
        expense: { label: 'Expense', color: 'hsl(var(--er))' },
    };

    // Format month labels as "Jan", "Feb", etc.
    function monthLabel(month: number): string {
        return new Date(2000, month - 1, 1).toLocaleString('default', { month: 'short' });
    }

    const chartData = $derived(
        months.map((m) => ({
            label: `${monthLabel(m.month)} ${m.year}`,
            income: m.income,
            expense: m.expense,
        }))
    );
</script>

<ChartContainer class="min-h-[220px] w-full" config={chartConfig}>
    <Chart
        data={chartData}
        padding={{ left: 16, bottom: 24 }}
        x="label"
        xScale={{ padding: 0.2 }}
        yDomain={[0, null]}>
        <Axis format={(v) => String(v)} placement="bottom" />
        <Bars color="var(--color-income)" key="income" radius={3} />
        <Bars color="var(--color-expense)" key="expense" radius={3} />
        <Tooltip.Root>
            <ChartTooltip indicator="line" />
        </Tooltip.Root>
    </Chart>
</ChartContainer>

{#if months.length === 0}
    <div class="flex flex-col items-center justify-center py-10 text-base-content/40">
        <i class="iconify mb-2 size-8 ph--chart-bar-bold"></i>
        <p class="text-sm">No transaction data yet</p>
    </div>
{/if}
