<script lang="ts">
    import DonutChart from '@components/ui/charts/donut-chart.svelte';

    interface CategoryItem {
        name: string;
        color: string;
        icon: string;
        total: number;
        percentage: number;
    }

    let {
        categories,
        periodTotal,
        periodLabel,
        emptyMessage = 'No spending data for this period',
    }: {
        categories: CategoryItem[];
        periodTotal: number;
        periodLabel?: string;
        emptyMessage?: string;
    } = $props();

    const slices = $derived(
        categories.map((c) => ({
            name: c.name,
            value: c.total,
            color: c.color,
        }))
    );

    const formattedTotal = $derived(
        periodTotal.toLocaleString('id-ID', {
            style: 'currency',
            currency: 'IDR',
            maximumFractionDigits: 0,
        })
    );
</script>

<div class="space-y-4">
    <DonutChart
        data={slices}
        {emptyMessage}
        centerText={formattedTotal}
        centerSubtext={periodLabel} />

    {#if categories.length > 0}
        <ul class="space-y-2">
            {#each categories as cat (cat.name)}
                <li class="flex items-center justify-between text-sm">
                    <div class="flex min-w-0 items-center gap-2">
                        <span
                            style="background-color: {cat.color}"
                            class="inline-block size-2.5 shrink-0 rounded-[2px]"></span>
                        <span class="truncate">{cat.name}</span>
                    </div>
                    <div class="ml-4 shrink-0 text-right">
                        <span class="font-mono font-medium"
                            >{cat.total.toLocaleString('id-ID', {
                                style: 'currency',
                                currency: 'IDR',
                                maximumFractionDigits: 0,
                            })}</span>
                        <span class="ml-1 text-base-content/50">{cat.percentage}%</span>
                    </div>
                </li>
            {/each}
        </ul>
    {/if}
</div>
