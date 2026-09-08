<script generics="TData extends RowData" lang="ts">
    import type { Column, RowData } from '@tanstack/svelte-table';
    import type { AppTableFeatures } from '@utilities/datatable.svelte';
    import type { HTMLAttributes } from 'svelte/elements';

    import { cn } from '@utilities/shadcn';

    import Button from '@components/ui/button.svelte';

    interface Props extends Pick<HTMLAttributes<HTMLDivElement>, 'class'> {
        column: Column<AppTableFeatures, TData>;
        title: string;
    }

    let { column, title, class: className, ...restProps }: Props = $props();
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
            onclick={column.getToggleSortingHandler()}
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
