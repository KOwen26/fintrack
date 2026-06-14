<script lang="ts">
    import type { RestProps } from '@type/index';
    import type { DataDisplay } from '@utilities/data-composer';
    import type { Snippet } from 'svelte';

    import DataListItem from './data-list-item.svelte';

    import { cn } from '@utilities/shadcn';

    interface Props extends RestProps {
        data: DataDisplay[];
        labelClass?: string;
        valueClass?: string;
        append?: Snippet;
        prepend?: Snippet;
    }

    let {
        data,
        class: _class,
        labelClass = '',
        valueClass = '',
        append,
        prepend,
    }: Props = $props();

    const classes = $derived(cn('@container w-full flex flex-col gap-2.5', _class));
</script>

<div class={classes}>
    {#if !!prepend}
        {@render prepend?.()}
    {/if}
    {#each data as { label, value, type, class: _class }, index (index)}
        {#if type === 'heading'}
            <div class={cn('mt-2 text-xl', index > 0 && 'border-t pt-5')}>
                {label}
            </div>
        {:else}
            <DataListItem class={_class} {labelClass} {valueClass} {label} {value} />
        {/if}
    {/each}
    {#if !!append}
        {@render append?.()}
    {/if}
</div>
