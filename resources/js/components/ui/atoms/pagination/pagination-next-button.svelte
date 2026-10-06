<script lang="ts">
    import { Pagination as PaginationPrimitive } from 'bits-ui';

    import { cn } from '@utilities/shadcn.js';

    import { buttonClassVariants as buttonVariants } from '@components/ui/button.svelte';

    let {
        ref = $bindable(null),
        class: className,
        children,
        ...restProps
    }: PaginationPrimitive.NextButtonProps = $props();
</script>

<PaginationPrimitive.NextButton
    class={cn(
        buttonVariants({
            size: 'default',
            variant: 'ghost',
            color: 'light',
            class: 'gap-1 px-2.5 sm:pr-2.5',
        }),
        className
    )}
    aria-label="Go to next page"
    children={children || Fallback}
    bind:ref
    {...restProps} />

{#snippet Fallback()}
    <span>Next</span>
    <i class="iconify size-4 solar--alt-arrow-right-line-duotone"></i>
{/snippet}
