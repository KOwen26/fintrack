<script lang="ts">
    import type { WithElementRef } from '@utilities/shadcn.js';
    import type { HTMLAttributes } from 'svelte/elements';

    import { cn } from '@utilities/shadcn.js';

    let {
        ref = $bindable(null),
        class: className,
        children,
        ...restProps
    }: WithElementRef<HTMLAttributes<HTMLUListElement>, HTMLUListElement> = $props();
</script>

<ul
    bind:this={ref}
    data-slot="sidebar-menu"
    class={cn('flex w-full min-w-0 flex-col gap-1', className)}
    data-sidebar="menu"
    {...restProps}>
    {@render children?.()}
</ul>
