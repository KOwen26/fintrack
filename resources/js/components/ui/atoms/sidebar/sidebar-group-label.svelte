<script lang="ts">
    import type { WithElementRef } from '@utilities/shadcn.js';
    import type { Snippet } from 'svelte';
    import type { HTMLAttributes } from 'svelte/elements';

    import { cn } from '@utilities/shadcn.js';

    let {
        ref = $bindable(null),
        children,
        child,
        class: className,
        ...restProps
    }: WithElementRef<HTMLAttributes<HTMLElement>> & {
        child?: Snippet<[{ props: Record<string, unknown> }]>;
    } = $props();

    const mergedProps = $derived({
        class: cn(
            'flex h-8 shrink-0 items-center rounded-md px-2 text-xs font-medium text-accent ring-sidebar-ring outline-hidden transition-[margin,opacity] duration-200 ease-linear focus-visible:ring-2 [&>svg]:size-4 [&>svg]:shrink-0',
            'group-data-[collapsible=icon]:h-0 group-data-[collapsible=icon]:opacity-0', //group-data-[collapsible=icon]:-mt-8
            className
        ),
        'data-slot': 'sidebar-group-label',
        'data-sidebar': 'group-label',
        ...restProps,
    });
</script>

{#if child}
    {@render child({ props: mergedProps })}
{:else}
    <div bind:this={ref} {...mergedProps}>
        {@render children?.()}
    </div>
    <hr class="my-auto hidden h-4 group-data-[collapsible=icon]:block" />
{/if}
