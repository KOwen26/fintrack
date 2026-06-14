<script lang="ts">
    import type { WithElementRef } from '@utilities/shadcn.js';
    import type { HTMLAttributes } from 'svelte/elements';

    import { cn } from '@utilities/shadcn.js';

    let {
        ref = $bindable(null),
        class: className,
        children,
        ...restProps
    }: WithElementRef<HTMLAttributes<HTMLTableSectionElement>> = $props();
</script>

<tbody
    bind:this={ref}
    class={cn('[&_tr:last-child]:border-0', className)}
    data-slot="table-body"
    {...restProps}>
    {@render children?.()}
</tbody>
