<script lang="ts">
    import type { WithElementRef } from '@utilities/shadcn.js';
    import type { HTMLAttributes } from 'svelte/elements';

    import { cn } from '@utilities/shadcn.js';

    let {
        ref = $bindable(null),
        children,
        class: className,
        ...restProps
    }: WithElementRef<HTMLAttributes<HTMLLIElement>> = $props();
</script>

<li
    bind:this={ref}
    data-slot="sidebar-menu-sub-item"
    class={cn('group/menu-sub-item relative', className)}
    data-sidebar="menu-sub-item"
    {...restProps}>
    {@render children?.()}
</li>
