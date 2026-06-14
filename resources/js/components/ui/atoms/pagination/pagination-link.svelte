<script lang="ts">
    import { Pagination as PaginationPrimitive } from 'bits-ui';

    import { cn } from '@utilities/shadcn.js';

    import { tvButtonVariants as buttonVariants } from '@components/ui/button.svelte';

    let {
        ref = $bindable(null),
        class: className,
        size = 'icon',
        isActive,
        page,
        children,
        ...restProps
    }: PaginationPrimitive.PageProps &
        Props & {
            isActive: boolean;
        } = $props();
</script>

{#snippet Fallback()}
    {page.value}
{/snippet}

<PaginationPrimitive.Page
    class={cn(
        buttonVariants({
            variant: isActive ? 'outline' : 'ghost',
            size,
        }),
        className
    )}
    aria-current={isActive ? 'page' : undefined}
    children={children || Fallback}
    data-active={isActive}
    data-slot="pagination-link"
    {page}
    bind:ref
    {...restProps} />
