<script lang="ts">
    import type { WithElementRef } from '@utilities/shadcn.js';
    import type { HTMLAttributes } from 'svelte/elements';

    import { cn } from '@utilities/shadcn.js';

    let {
        ref = $bindable(null),
        class: className,
        children,
        ...restProps
    }: WithElementRef<HTMLAttributes<HTMLElement>> = $props();
</script>

<div
    bind:this={ref}
    class={cn(
        'flex min-h-0 flex-1 flex-col overflow-auto group-data-[collapsible=icon]:overflow-hidden',
        className
    )}
    data-sidebar="content"
    data-slot="sidebar-content"
    {...restProps}>
    {@render children?.()}
</div>
