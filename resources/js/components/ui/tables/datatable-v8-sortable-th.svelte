<script lang="ts" module>
    export interface SortableTableHeadProps extends Pick<HTMLAttributes<HTMLDivElement>, 'class'> {
        column: any; //Use type any for easier column def use
        title: string;
    }
</script>

<script generics="TData, TValue" lang="ts">
    import type { Column } from '@tanstack/table-core';
    import type { HTMLAttributes } from 'svelte/elements';

    import { cn } from '@utilities/shadcn';

    import Button from '@components/ui/button.svelte';

    let {
        column: _column,
        title,
        class: className,
        ...restProps
    }: SortableTableHeadProps = $props();

    const column = $derived<Column<TData, TValue>>(_column); //Reapply type
</script>

{#if !column?.getCanSort()}
    <div class={cn(className)} {...restProps}>
        {title}
    </div>
{:else}
    <div class={cn('-mx-2 flex items-center', className)} {...restProps}>
        <Button
            class="h-8 gap-2 rounded px-2"
            color="light"
            onclick={() => {
                const isDesc =
                    column.getIsSorted() === undefined
                        ? false
                        : column.getIsSorted() === 'asc'
                          ? true
                          : undefined;

                column.toggleSorting(isDesc);
            }}
            variant="ghost">
            <span>
                {title}
            </span>
            {#if column.getIsSorted() === 'desc'}
                <i class="iconify size-4 solar--arrow-down-line-duotone"></i>
            {:else if column.getIsSorted() === 'asc'}
                <i class="iconify size-4 solar--arrow-up-line-duotone"></i>
            {:else}
                <i class="iconify size-4 solar--sort-vertical-line-duotone"></i>
            {/if}
        </Button>
    </div>
{/if}
