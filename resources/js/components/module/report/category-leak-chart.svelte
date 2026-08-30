<script lang="ts">
    import type { ChartConfig } from '@components/ui/atoms/chart';

    import { Arc, Chart, Pie, Tooltip } from 'layerchart';

    import { ChartContainer, ChartTooltip } from '@components/ui/atoms/chart';

    interface CategoryItem {
        name: string;
        color: string;
        icon: string;
        total: number;
        percentage: number;
    }

    let { categories, period_total }: { categories: CategoryItem[]; period_total: number } =
        $props();

    // Build ChartConfig keyed by category name — colors come from category data
    const chartConfig = $derived<ChartConfig>(
        Object.fromEntries(categories.map((c) => [c.name, { label: c.name, color: c.color }]))
    );

    function formatIDR(value: number): string {
        return value.toLocaleString('id-ID', {
            style: 'currency',
            currency: 'IDR',
            maximumFractionDigits: 0,
        });
    }
</script>

{#if categories.length === 0}
    <div class="flex flex-col items-center justify-center py-10 text-base-content/40">
        <i class="iconify mb-2 size-8 ph--chart-donut-bold"></i>
        <p class="text-sm">No expense data for this period</p>
    </div>
{:else}
    <ChartContainer class="mx-auto min-h-[200px] w-full max-w-xs" config={chartConfig}>
        <Chart data={categories} key="name" value="total">
            <Pie innerRadius={50}>
                {#each categories as cat (cat.name)}
                    <Arc color={cat.color} />
                {/each}
            </Pie>
            <Tooltip.Root>
                <ChartTooltip indicator="dot" />
            </Tooltip.Root>
        </Chart>
    </ChartContainer>

    <!-- Legend / ranked list -->
    <ul class="mt-4 space-y-2">
        {#each categories as cat (cat.name)}
            <li class="flex items-center justify-between text-sm">
                <div class="flex items-center gap-2 min-w-0">
                    <span
                        style="background-color: {cat.color}"
                        class="inline-block size-2.5 shrink-0 rounded-[2px]"></span>
                    <span class="truncate">{cat.name}</span>
                </div>
                <div class="ml-4 shrink-0 text-right">
                    <span class="font-mono font-medium">{formatIDR(cat.total)}</span>
                    <span class="ml-1 text-base-content/50">{cat.percentage}%</span>
                </div>
            </li>
        {/each}
    </ul>
{/if}
