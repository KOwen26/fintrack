<script lang="ts">
    import type { WithElementRef } from '@utilities/shadcn.js';
    import type { HTMLAttributes } from 'svelte/elements';

    import { cn } from '@utilities/shadcn.js';

    let {
        ref = $bindable(null),
        class: className,
        children,
        ...restProps
    }: WithElementRef<HTMLAttributes<HTMLElement>> = $props();
</script>

<caption
    bind:this={ref}
    class={cn('text-muted-foreground mt-4 text-sm', className)}
    data-slot="table-caption"
    {...restProps}>
    {@render children?.()}
</caption>
