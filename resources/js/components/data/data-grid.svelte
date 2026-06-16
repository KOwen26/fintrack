<script lang="ts">
    import type { RestProps } from '@type/index';
    import type { DataDisplay } from '@utilities/data-composer';
    import type { Snippet } from 'svelte';

    import DataGridItem from './data-grid-item.svelte';

    import { cn } from '@utilities/shadcn';

    const directionClassMap = {
        'left-to-right': 'grid-flow-row grid-cols-2',
        'top-to-bottom': 'grid-flow-col grid-cols-2',
    };

    interface DataGridProps extends RestProps {
        data: DataDisplay[];
        direction?: keyof typeof directionClassMap;
        labelClass?: string;
        valueClass?: string;
        append?: Snippet;
        prepend?: Snippet;
    }

    let {
        direction = 'left-to-right',
        data,
        class: _class,
        labelClass = '',
        valueClass = '',
        append,
        prepend,
    }: DataGridProps = $props();

    const classes = $derived(
        cn('@container grid w-full gap-5', directionClassMap[direction], _class)
    );
</script>

<div
    style:grid-template-rows={direction === 'top-to-bottom'
        ? `repeat(${Math.ceil(data?.length / 2)}, auto)`
        : undefined}
    class={classes}>
    {#if !!prepend}
        {@render prepend?.()}
    {/if}
    {#each data as { label, value, type, class: _class }, index (index)}
        {#if type === 'heading'}
            <div class="col-span-2 mt-5 text-xl">
                {label}
            </div>
        {:else}
            <DataGridItem class={_class} {labelClass} {valueClass} {label} {value} />
        {/if}
    {/each}
    {#if !!append}
        {@render append?.()}
    {/if}
</div>
