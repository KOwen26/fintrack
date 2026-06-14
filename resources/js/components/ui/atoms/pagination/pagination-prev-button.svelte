<script lang="ts">
    import { Pagination as PaginationPrimitive } from 'bits-ui';

    import { cn } from '@utilities/shadcn.js';

    import { tvButtonVariants as buttonVariants } from '@components/ui/button.svelte';

    let {
        ref = $bindable(null),
        class: className,
        children,
        ...restProps
    }: PaginationPrimitive.PrevButtonProps = $props();
</script>

{#snippet Fallback()}
    <i class="iconify ph--caret-left-duotone size-4"></i>
    <span>Previous</span>
{/snippet}

<PaginationPrimitive.PrevButton
    class={cn(
        buttonVariants({
            size: 'default',
            variant: 'ghost',
            class: 'gap-1 px-2.5 sm:pl-2.5',
        }),
        className
    )}
    aria-label="Go to previous page"
    children={children || Fallback}
    bind:ref
    {...restProps} />
