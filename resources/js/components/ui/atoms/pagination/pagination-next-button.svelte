<script lang="ts">
    import { Pagination as PaginationPrimitive } from 'bits-ui';

    import { cn } from '@utilities/shadcn.js';

    import { tvButtonVariants as buttonVariants } from '@components/ui/button.svelte';

    let {
        ref = $bindable(null),
        class: className,
        children,
        ...restProps
    }: PaginationPrimitive.NextButtonProps = $props();
</script>

{#snippet Fallback()}
    <span>Next</span>
    <i class="iconify ph--caret-right-duotone size-4"></i>
{/snippet}

<PaginationPrimitive.NextButton
    class={cn(
        buttonVariants({
            size: 'default',
            variant: 'ghost',
            class: 'gap-1 px-2.5 sm:pr-2.5',
        }),
        className
    )}
    aria-label="Go to next page"
    children={children || Fallback}
    bind:ref
    {...restProps} />
