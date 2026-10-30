<script lang="ts">
    import type { WithElementRef } from '@utilities/shadcn.js';
    import type { HTMLAttributes } from 'svelte/elements';

    import { cn } from '@utilities/shadcn.js';

    let {
        ref = $bindable(null),
        class: className,
        children,
        ...restProps
    }: WithElementRef<HTMLAttributes<HTMLDivElement>> = $props();
</script>

<div
    bind:this={ref}
    data-slot="card-action"
    class={cn(
        'has-data-[slot=card-description]/card-header:row-span-2 has-data-[slot=card-description]/card-header:self-start col-start-2 row-start-1 self-center justify-self-end',
        className
    )}
    {...restProps}>
    {@render children?.()}
</div>
