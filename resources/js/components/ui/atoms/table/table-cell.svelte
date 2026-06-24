<script lang="ts">
    import type { WithElementRef } from '@utilities/shadcn.js';
    import type { HTMLTdAttributes } from 'svelte/elements';

    import { cn } from '@utilities/shadcn.js';

    let {
        ref = $bindable(null),
        class: className,
        children,
        ...restProps
    }: WithElementRef<HTMLTdAttributes> = $props();
</script>

<td
    bind:this={ref}
    data-slot="table-cell"
    class={cn(
        'bg-clip-padding p-2.5 align-middle whitespace-nowrap first-of-type:pl-5 last-of-type:pr-5 [&:has([role=checkbox])]:pr-0',
        className
    )}
    {...restProps}>
    {@render children?.()}
</td>
