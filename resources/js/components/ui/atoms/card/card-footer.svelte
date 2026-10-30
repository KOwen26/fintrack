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
    data-slot="card-footer"
    class={cn(
        'flex items-center rounded-b-xl border-t border-border bg-muted/50 p-(--card-spacing) group-data-[variant=flat]/card:rounded-b-none group-data-[variant=flat]/card:border-t-0 group-data-[variant=flat]/card:bg-transparent',
        className
    )}
    {...restProps}>
    {@render children?.()}
</div>
