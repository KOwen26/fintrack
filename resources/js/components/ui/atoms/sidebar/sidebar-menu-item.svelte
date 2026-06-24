<script lang="ts">
    import type { WithElementRef } from '@utilities/shadcn.js';
    import type { HTMLAttributes } from 'svelte/elements';

    import { cn } from '@utilities/shadcn.js';

    let {
        ref = $bindable(null),
        class: className,
        children,
        ...restProps
    }: WithElementRef<HTMLAttributes<HTMLLIElement>, HTMLLIElement> = $props();
</script>

<li
    bind:this={ref}
    data-slot="sidebar-menu-item"
    class={cn('group/menu-item relative', 'group-data-[collapsible=icon]:mx-auto', className)}
    data-sidebar="menu-item"
    {...restProps}>
    {@render children?.()}
</li>
