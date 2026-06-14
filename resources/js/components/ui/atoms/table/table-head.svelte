<script lang="ts">
    import type { WithElementRef } from '@utilities/shadcn.js';
    import type { HTMLThAttributes } from 'svelte/elements';

    import { cn } from '@utilities/shadcn.js';

    let {
        ref = $bindable(null),
        class: className,
        children,
        ...restProps
    }: WithElementRef<HTMLThAttributes> = $props();
</script>

<th
    bind:this={ref}
    class={cn(
        'text-foreground h-10 border-neutral-400 bg-clip-padding px-3 text-left align-middle font-medium whitespace-nowrap first-of-type:pl-5 last-of-type:pr-5 [&:has([role=checkbox])]:pr-0',
        className
    )}
    data-slot="table-head"
    {...restProps}>
    {@render children?.()}
</th>
