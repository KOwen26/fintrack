<script lang="ts">
    import { Pagination as PaginationPrimitive } from 'bits-ui';

    import { cn } from '@utilities/shadcn.js';

    import { buttonClassVariants as buttonVariants } from '@components/ui/button.svelte';

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

<PaginationPrimitive.Page
    data-slot="pagination-link"
    class={cn(
        buttonVariants({
            variant: isActive ? 'outline' : 'ghost',
            color: 'light',
            size,
        }),
        className
    )}
    aria-current={isActive ? 'page' : undefined}
    children={children || Fallback}
    data-active={isActive}
    {page}
    bind:ref
    {...restProps} />

{#snippet Fallback()}
    {page.value}
{/snippet}
