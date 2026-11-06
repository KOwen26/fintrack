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
    data-slot="table-head"
    class={cn(
        'h-10 bg-clip-padding px-2.5 text-left align-middle font-medium whitespace-nowrap text-foreground first-of-type:pl-5 last-of-type:pr-5 [&:has([role=checkbox])]:pr-0',
        className
    )}
    {...restProps}>
    {@render children?.()}
</th>
