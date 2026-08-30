<script lang="ts">
    import { Arc, PieChart } from 'layerchart';

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
        selectedKey = $bindable(null),
    }: {
        data: DonutSlice[];
        centerText: string;
        centerSubtext?: string;
        innerRadius?: number;
        emptyMessage?: string;
        class?: string;
        selectedKey?: string | null;
    } = $props();

    function handleArcClick(_e: MouseEvent, key: string) {
        selectedKey = selectedKey === key ? null : key;
    }
</script>

{#if data.length === 0}
    <div
        class={cn(
            'flex aspect-square items-center justify-center text-base-content/40',
            className
        )}>
        <p class="text-sm">{emptyMessage}</p>
    </div>
{:else}
    <div class={cn('flex aspect-square justify-center overflow-visible text-xs', className)}>
        <PieChart
            c="color"
            {data}
            {innerRadius}
            key="name"
            label="name"
            props={{ pie: { motion: 'spring' } }}
            value="value">
            {#snippet arc({ props: arcProps, key: keyAccessor })}
                {const key = $derived(keyAccessor(arcProps.data))}
                {const isSelected = $derived(selectedKey === key)}

                <Arc
                    {...arcProps}
                    offset={isSelected ? 10 : 0}
                    onclick={(e) => handleArcClick(e, key)}
                    opacity={selectedKey === null || isSelected ? 1 : 0.4} />
            {/snippet}
            {#snippet aboveMarks()}
                <text
                    dominant-baseline="middle"
                    fill="currentColor"
                    font-size="24"
                    font-weight="bold"
                    text-anchor="middle">
                    {centerText}
                </text>

                {#if centerSubtext}
                    <text
                        dominant-baseline="middle"
                        dy="22"
                        fill="currentColor"
                        font-size="14"
                        opacity="0.5"
                        text-anchor="middle">
                        {centerSubtext}
                    </text>
                {/if}
            {/snippet}
        </PieChart>
    </div>
{/if}
