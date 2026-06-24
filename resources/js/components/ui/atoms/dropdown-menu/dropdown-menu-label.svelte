<script lang="ts">
    import type { WithElementRef } from '@utilities/shadcn.js';
    import type { HTMLAttributes } from 'svelte/elements';

    import { cn } from '@utilities/shadcn.js';

    let {
        ref = $bindable(null),
        class: className,
        inset,
        children,
        ...restProps
    }: WithElementRef<HTMLAttributes<HTMLDivElement>> & {
        inset?: boolean;
    } = $props();
</script>

<div
    bind:this={ref}
    data-slot="dropdown-menu-label"
    class={cn('px-2 py-1.5 text-sm font-semibold data-[inset]:pl-8', className)}
    data-inset={inset}
    {...restProps}>
    {@render children?.()}
</div>
