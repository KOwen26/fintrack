<script lang="ts">
    import { Pagination as PaginationPrimitive } from 'bits-ui';

    import { cn } from '@utilities/shadcn.js';

    import { buttonClassVariants as buttonVariants } from '@components/ui/button.svelte';

    let {
        ref = $bindable(null),
        class: className,
        children,
        ...restProps
    }: PaginationPrimitive.PrevButtonProps = $props();
</script>

<PaginationPrimitive.PrevButton
    class={cn(
        buttonVariants({
            size: 'default',
            variant: 'ghost',
            color: 'light',
            class: 'gap-1 px-2.5 sm:pl-2.5',
        }),
        className
    )}
    aria-label="Go to previous page"
    children={children || Fallback}
    bind:ref
    {...restProps} />

{#snippet Fallback()}
    <i class="iconify size-4 solar--alt-arrow-left-line-duotone"></i>
    <span>Previous</span>
{/snippet}
