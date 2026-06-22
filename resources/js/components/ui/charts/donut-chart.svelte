<script lang="ts">
    import { PieChart } from 'layerchart';

    import { cn } from '@utilities/shadcn.js';

    interface DonutSlice {
        name: string;
        value: number;
        color: string;
    }

    let {
        data,
        centerText,
        centerSubtext,
        innerRadius = 0.6,
        emptyMessage = 'No data',
        class: className,
    }: {
        data: DonutSlice[];
        centerText: string;
        centerSubtext?: string;
        innerRadius?: number;
        emptyMessage?: string;
        class?: string;
    } = $props();
</script>

{#if data.length === 0}
    <div
        class={cn('flex aspect-video items-center justify-center text-base-content/40', className)}>
        <p class="text-sm">{emptyMessage}</p>
    </div>
{:else}
    <div class={cn('flex aspect-video justify-center overflow-visible text-xs', className)}>
        <PieChart {data} key="name" label="name" value="value" c="color" {innerRadius}>
            {#snippet aboveMarks()}
                <text
                    text-anchor="middle"
                    dominant-baseline="middle"
                    font-size="24"
                    font-weight="bold"
                    fill="currentColor">
                    {centerText}
                </text>
                {#if centerSubtext}
                    <text
                        text-anchor="middle"
                        dominant-baseline="middle"
                        dy="22"
                        font-size="14"
                        fill="currentColor"
                        opacity="0.5">
                        {centerSubtext}
                    </text>
                {/if}
            {/snippet}
        </PieChart>
    </div>
{/if}
