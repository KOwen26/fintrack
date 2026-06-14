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

<thead
    bind:this={ref}
    class={cn('border-neutral-400 bg-neutral-200/60 [&_tr]:border-b', className)}
    data-slot="table-header"
    {...restProps}>
    {@render children?.()}
</thead>
